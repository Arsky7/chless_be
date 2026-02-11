<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_size_id',
        'quantity',
        'reserved_quantity',
        'location_code',
        'min_stock',
        'max_stock',
        'last_counted_at',
        'last_counted_by',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'last_counted_at' => 'datetime',
    ];

    protected $appends = [
        'available_quantity',
        'is_low_stock',
        'is_out_of_stock',
        'stock_value'
    ];

    // ============ RELATIONSHIPS ============
    
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function lastCountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_counted_by');
    }

    // ============ ACCESSORS ============
    
    public function getAvailableQuantityAttribute()
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function getIsLowStockAttribute()
    {
        return $this->available_quantity <= $this->min_stock;
    }

    public function getIsOutOfStockAttribute()
    {
        return $this->available_quantity <= 0;
    }

    public function getStockValueAttribute()
    {
        return $this->quantity * ($this->productSize->cost ?? 0);
    }

    public function getFormattedStockValueAttribute()
    {
        return 'Rp ' . number_format($this->stock_value, 0, ',', '.');
    }

    public function getProductAttribute()
    {
        return $this->productSize->product;
    }

    public function getSizeAttribute()
    {
        return $this->productSize->size;
    }

    // ============ SCOPES ============
    
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity - reserved_quantity <= min_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereRaw('quantity - reserved_quantity <= 0');
    }

    public function scopeByLocation($query, $locationCode)
    {
        return $query->where('location_code', $locationCode);
    }

    // ============ HELPERS ============
    
    public function reserve($quantity)
    {
        if ($this->available_quantity < $quantity) {
            throw new \Exception('Insufficient stock');
        }
        
        $this->increment('reserved_quantity', $quantity);
        
        return $this;
    }

    public function release($quantity)
    {
        if ($this->reserved_quantity < $quantity) {
            throw new \Exception('Cannot release more than reserved');
        }
        
        $this->decrement('reserved_quantity', $quantity);
        
        return $this;
    }

    public function decrease($quantity)
    {
        if ($this->quantity < $quantity) {
            throw new \Exception('Insufficient stock');
        }
        
        $this->decrement('quantity', $quantity);
        
        if ($this->reserved_quantity > $this->quantity) {
            $this->update(['reserved_quantity' => $this->quantity]);
        }
        
        return $this;
    }

    public function increase($quantity)
    {
        $this->increment('quantity', $quantity);
        
        return $this;
    }

    public function countStock($physicalQuantity, $userId)
    {
        $difference = $physicalQuantity - $this->quantity;
        
        $this->update([
            'quantity' => $physicalQuantity,
            'last_counted_at' => now(),
            'last_counted_by' => $userId,
        ]);
        
        if ($difference != 0) {
            // Log stock adjustment
            StockMovement::create([
                'movement_number' => 'ADJ-' . uniqid(),
                'warehouse_id' => $this->warehouse_id,
                'product_size_id' => $this->product_size_id,
                'user_id' => $userId,
                'type' => 'adjustment',
                'quantity' => abs($difference),
                'before_quantity' => $this->quantity - $difference,
                'after_quantity' => $this->quantity,
                'reason' => 'Stock opname',
                'notes' => "Difference: {$difference}",
            ]);
        }
        
        return $this;
    }
}