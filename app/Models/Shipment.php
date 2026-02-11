<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'courier',
        'service',
        'shipping_address',
        'shipping_cost',
        'insurance_cost',
        'package_dimensions',
        'package_weight',
        'status',
        'tracking_history',
        'estimated_delivery',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'shipping_cost' => 'decimal:2',
        'insurance_cost' => 'decimal:2',
        'package_dimensions' => 'array',
        'package_weight' => 'decimal:2',
        'tracking_history' => 'array',
        'estimated_delivery' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // RELATIONSHIPS
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // SCOPES
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePickedUp($query)
    {
        return $query->where('status', 'picked_up');
    }

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeOutForDelivery($query)
    {
        return $query->where('status', 'out_for_delivery');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    public function scopeByCourier($query, $courier)
    {
        return $query->where('courier', $courier);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ACCESSORS
    public function getFormattedShippingCostAttribute()
    {
        return 'Rp ' . number_format($this->shipping_cost, 0, ',', '.');
    }

    public function getFormattedInsuranceCostAttribute()
    {
        return 'Rp ' . number_format($this->insurance_cost, 0, ',', '.');
    }

    public function getTotalCostAttribute()
    {
        return $this->shipping_cost + $this->insurance_cost;
    }

    public function getFormattedTotalCostAttribute()
    {
        return 'Rp ' . number_format($this->total_cost, 0, ',', '.');
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Menunggu Pengiriman',
            'picked_up' => 'Sudah Diambil',
            'in_transit' => 'Dalam Perjalanan',
            'out_for_delivery' => 'Sedang Dikirim',
            'delivered' => 'Terkirim',
            'failed' => 'Gagal',
            'returned' => 'Dikembalikan',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    public function getIsDeliveredAttribute()
    {
        return $this->status === 'delivered';
    }

    public function getIsInTransitAttribute()
    {
        return in_array($this->status, ['picked_up', 'in_transit', 'out_for_delivery']);
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function getDeliveryAddressAttribute()
    {
        $address = $this->shipping_address ?? [];
        
        $parts = [
            $address['recipient_name'] ?? '',
            $address['phone'] ?? '',
            $address['street_address'] ?? '',
            $address['subdistrict'] ?? '',
            $address['district'] ?? '',
            $address['city'] ?? '',
            $address['province'] ?? '',
            $address['postal_code'] ?? '',
        ];
        
        return implode("\n", array_filter($parts));
    }

    public function getDaysInTransitAttribute()
    {
        if (!$this->shipped_at) {
            return null;
        }
        
        $start = $this->shipped_at;
        $end = $this->delivered_at ?? now();
        
        return $start->diffInDays($end);
    }

    // HELPERS
    public function markAsPickedUp()
    {
        $this->update([
            'status' => 'picked_up',
            'shipped_at' => now(),
        ]);
        
        // Update order status
        $this->order->markAsShipped($this->tracking_number);
        
        $this->addTrackingHistory('Paket telah diambil oleh kurir');
        
        return $this;
    }

    public function markAsInTransit()
    {
        $this->update(['status' => 'in_transit']);
        $this->addTrackingHistory('Paket dalam perjalanan ke hub pengiriman');
        
        return $this;
    }

    public function markAsOutForDelivery()
    {
        $this->update(['status' => 'out_for_delivery']);
        $this->addTrackingHistory('Paket sedang dikirim ke alamat penerima');
        
        return $this;
    }

    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        
        // Update order status
        $this->order->markAsDelivered();
        
        $this->addTrackingHistory('Paket telah diterima oleh penerima');
        
        return $this;
    }

    public function markAsFailed($reason = null)
    {
        $this->update(['status' => 'failed']);
        $this->addTrackingHistory('Pengiriman gagal: ' . $reason);
        
        return $this;
    }

    public function markAsReturned($reason = null)
    {
        $this->update(['status' => 'returned']);
        $this->addTrackingHistory('Paket dikembalikan: ' . $reason);
        
        return $this;
    }

    public function addTrackingHistory($description, $location = null)
    {
        $history = $this->tracking_history ?? [];
        
        $history[] = [
            'timestamp' => now()->toDateTimeString(),
            'status' => $this->status,
            'description' => $description,
            'location' => $location,
        ];
        
        $this->update(['tracking_history' => $history]);
        
        return $this;
    }

    public function getTrackingUrl()
    {
        $couriers = [
            'jne' => 'https://www.jne.co.id/id/tracking/trace',
            'jnt' => 'https://www.jet.co.id/tracking',
            'tiki' => 'https://www.tiki.id/id/tracking',
            'pos' => 'https://www.posindonesia.co.id/id/tracking',
            'sicepat' => 'https://www.sicepat.com/check-awb',
            'anteraja' => 'https://anteraja.id/tracking',
            'gosend' => 'https://www.gojek.com/gosend/tracking/',
            'grab' => 'https://www.grab.com/id/track/',
        ];
        
        $url = $couriers[strtolower($this->courier)] ?? null;
        
        if ($url && $this->tracking_number) {
            return $url . '?no=' . $this->tracking_number;
        }
        
        return null;
    }

    public function updateTracking()
    {
        // This would call the courier's API to get latest tracking info
        // For now, we'll just return the current status
        
        return $this->status;
    }

    public function getPackageDimensionsText()
    {
        $dimensions = $this->package_dimensions ?? [];
        
        if (empty($dimensions)) {
            return '-';
        }
        
        $parts = [];
        
        if (isset($dimensions['length'])) {
            $parts[] = 'P: ' . $dimensions['length'] . 'cm';
        }
        
        if (isset($dimensions['width'])) {
            $parts[] = 'L: ' . $dimensions['width'] . 'cm';
        }
        
        if (isset($dimensions['height'])) {
            $parts[] = 'T: ' . $dimensions['height'] . 'cm';
        }
        
        return implode(' × ', $parts);
    }

    public function calculateEstimatedDelivery()
    {
        if (!$this->shipped_at) {
            return null;
        }
        
        $estimatedDays = 3; // Default
        $service = strtolower($this->service);
        
        if (str_contains($service, 'express') || str_contains($service, 'kilat')) {
            $estimatedDays = 1;
        } elseif (str_contains($service, 'reguler') || str_contains($service, 'regular')) {
            $estimatedDays = 3;
        } elseif (str_contains($service, 'ekonomi') || str_contains($service, 'economy')) {
            $estimatedDays = 5;
        }
        
        return $this->shipped_at->addDays($estimatedDays);
    }

    public static function createFromOrder($order, $courier, $service, $shippingCost)
    {
        $shipment = self::create([
            'order_id' => $order->id,
            'tracking_number' => strtoupper(uniqid()),
            'courier' => $courier,
            'service' => $service,
            'shipping_address' => $order->address->toArray(),
            'shipping_cost' => $shippingCost,
            'package_weight' => $order->items->sum(function($item) {
                return ($item->product->weight_gram ?? 300) * $item->quantity;
            }) / 1000, // Convert to kg
            'status' => 'pending',
            'estimated_delivery' => now()->addDays(3),
        ]);
        
        $shipment->addTrackingHistory('Shipment created');
        
        return $shipment;
    }
}