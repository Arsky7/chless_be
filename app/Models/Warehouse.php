<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'address',
        'city',
        'province',
        'postal_code',
        'country',
        'contact_person',
        'contact_phone',
        'contact_email',
        'latitude',
        'longitude',
        'capacity',
        'used_capacity',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'capacity' => 'integer',
        'used_capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_address',
        'used_capacity_percentage',
        'is_full'
    ];

    // ============ RELATIONSHIPS ============
    
    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function productSizes()
    {
        return $this->belongsToMany(ProductSize::class, 'warehouse_stocks')
                    ->withPivot('quantity', 'reserved_quantity', 'location_code')
                    ->withTimestamps();
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function inboundMovements()
    {
        return $this->hasMany(StockMovement::class, 'to_warehouse_id');
    }

    public function outboundMovements()
    {
        return $this->hasMany(StockMovement::class, 'from_warehouse_id');
    }

    // ============ SCOPES ============
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('city', 'LIKE', "%{$city}%");
    }

    public function scopeMain($query)
    {
        return $query->where('type', 'main');
    }

    // ============ ACCESSORS ============
    
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->province,
            $this->postal_code
        ]);
        
        return implode(', ', $parts);
    }

    public function getUsedCapacityPercentageAttribute()
    {
        if (!$this->capacity) {
            return 0;
        }
        
        return round(($this->used_capacity / $this->capacity) * 100, 2);
    }

    public function getIsFullAttribute()
    {
        if (!$this->capacity) {
            return false;
        }
        
        return $this->used_capacity >= $this->capacity;
    }

    public function getContactInfoAttribute()
    {
        $info = [];
        
        if ($this->contact_person) {
            $info[] = $this->contact_person;
        }
        if ($this->contact_phone) {
            $info[] = $this->contact_phone;
        }
        if ($this->contact_email) {
            $info[] = $this->contact_email;
        }
        
        return implode(' | ', $info);
    }

    public function getTotalStockValueAttribute()
    {
        return $this->stocks()
            ->join('product_sizes', 'warehouse_stocks.product_size_id', '=', 'product_sizes.id')
            ->sum(\DB::raw('warehouse_stocks.quantity * product_sizes.cost'));
    }

    public function getFormattedStockValueAttribute()
    {
        return 'Rp ' . number_format($this->total_stock_value, 0, ',', '.');
    }

    // ============ HELPERS ============
    
    public function getStockForProductSize($productSizeId)
    {
        return $this->stocks()
            ->where('product_size_id', $productSizeId)
            ->first();
    }

    public function getLowStockItems()
    {
        return $this->stocks()
            ->with('productSize.product')
            ->whereRaw('quantity - reserved_quantity <= min_stock')
            ->get();
    }

    public function getOutOfStockItems()
    {
        return $this->stocks()
            ->with('productSize.product')
            ->whereRaw('quantity - reserved_quantity <= 0')
            ->get();
    }

    public function getAvailableCapacity()
    {
        if (!$this->capacity) {
            return null;
        }
        
        return $this->capacity - $this->used_capacity;
    }

    public function updateUsedCapacity()
    {
        $totalQuantity = $this->stocks()->sum('quantity');
        $this->update(['used_capacity' => $totalQuantity]);
        
        return $this;
    }

    public function canFulfillOrder($items)
    {
        foreach ($items as $item) {
            $stock = $this->getStockForProductSize($item['product_size_id']);
            
            if (!$stock || $stock->available_quantity < $item['quantity']) {
                return false;
            }
        }
        
        return true;
    }

    public static function getNearestWarehouse($city, $province)
    {
        // Simplified - in production use geolocation
        return self::active()
            ->where(function($q) use ($city, $province) {
                $q->where('city', $city)
                  ->orWhere('province', $province);
            })
            ->orderBy('type', 'desc') // main first
            ->first();
    }

    public function reserveStock($productSizeId, $quantity)
    {
        $stock = $this->getStockForProductSize($productSizeId);
        
        if (!$stock) {
            throw new \Exception('Product not found in warehouse');
        }
        
        if ($stock->available_quantity < $quantity) {
            throw new \Exception('Insufficient stock');
        }
        
        $stock->increment('reserved_quantity', $quantity);
        
        return $stock;
    }

    public function releaseStock($productSizeId, $quantity)
    {
        $stock = $this->getStockForProductSize($productSizeId);
        
        if ($stock) {
            $stock->decrement('reserved_quantity', $quantity);
        }
        
        return $stock;
    }
}