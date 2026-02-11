<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_number',
        'warehouse_id',
        'product_size_id',
        'user_id',
        'type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'reference_type',
        'reference_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'reason',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'before_quantity' => 'integer',
        'after_quantity' => 'integer',
    ];

    protected $appends = [
        'type_label',
        'formatted_quantity'
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute()
    {
        return [
            'in' => 'Barang Masuk',
            'out' => 'Barang Keluar',
            'transfer' => 'Transfer',
            'adjustment' => 'Penyesuaian',
            'return' => 'Retur',
            'damaged' => 'Rusak',
            'expired' => 'Kadaluarsa'
        ][$this->type] ?? $this->type;
    }

    public function getFormattedQuantityAttribute()
    {
        return number_format($this->quantity, 0, ',', '.');
    }

    public function getTypeBadgeAttribute()
    {
        return [
            'in' => 'badge-success',
            'out' => 'badge-danger',
            'transfer' => 'badge-info',
            'adjustment' => 'badge-warning',
            'return' => 'badge-primary',
            'damaged' => 'badge-dark',
            'expired' => 'badge-dark'
        ][$this->type] ?? 'badge-secondary';
    }
}
