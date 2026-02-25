<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    protected $fillable = [
        'return_number',
        'order_id',
        'user_id',
        'product_id',
        'reason',
        'status',
        'status_date',
        'video_status',
        'refund_amount',
        'request_date',
    ];

    protected $casts = [
        'status_date' => 'datetime',
        'request_date' => 'datetime',
        'refund_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
