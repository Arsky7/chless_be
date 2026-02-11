<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'courier',
        'description',
        'type',
        'base_cost',
        'cost_rules',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'is_cod_available',
        'is_insurance_available',
        'insurance_rate',
        'zones',
        'limitations',
        'sort_order',
    ];

    protected $casts = [
        'base_cost' => 'decimal:2',
        'cost_rules' => 'array',
        'is_active' => 'boolean',
        'is_cod_available' => 'boolean',
        'is_insurance_available' => 'boolean',
        'insurance_rate' => 'decimal:2',
        'zones' => 'array',
        'limitations' => 'array',
    ];

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCodAvailable($query)
    {
        return $query->where('is_cod_available', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCourier($query, $courier)
    {
        return $query->where('courier', $courier);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ACCESSORS
    public function getFormattedBaseCostAttribute()
    {
        return 'Rp ' . number_format($this->base_cost, 0, ',', '.');
    }

    public function getTypeLabelAttribute()
    {
        $labels = [
            'regular' => 'Reguler',
            'express' => 'Express',
            'same_day' => 'Same Day',
            'instant' => 'Instant',
        ];
        
        return $labels[$this->type] ?? $this->type;
    }

    public function getEstimatedDaysAttribute()
    {
        if ($this->estimated_days_min && $this->estimated_days_max) {
            if ($this->estimated_days_min === $this->estimated_days_max) {
                return $this->estimated_days_min . ' hari';
            }
            return $this->estimated_days_min . '-' . $this->estimated_days_max . ' hari';
        }
        
        return null;
    }

    // HELPERS
    public function calculateCost($weight = 0, $distance = 0, $value = 0)
    {
        $cost = $this->base_cost;
        
        // Apply cost rules if any
        $rules = $this->cost_rules ?? [];
        
        if (!empty($rules)) {
            // Example: {"weight": {"0-1000": 5000, "1001-5000": 10000}}
            foreach ($rules as $ruleType => $ruleValues) {
                switch ($ruleType) {
                    case 'weight':
                        $cost += $this->calculateWeightCost($weight, $ruleValues);
                        break;
                    case 'distance':
                        $cost += $this->calculateDistanceCost($distance, $ruleValues);
                        break;
                    case 'value':
                        $cost += $this->calculateValueCost($value, $ruleValues);
                        break;
                }
            }
        }
        
        // Add insurance if applicable
        if ($this->is_insurance_available && $this->insurance_rate && $value > 0) {
            $insurance = $value * ($this->insurance_rate / 100);
            $cost += $insurance;
        }
        
        return $cost;
    }

    private function calculateWeightCost($weight, $rules)
    {
        foreach ($rules as $range => $additionalCost) {
            list($min, $max) = explode('-', $range);
            
            if ($weight >= $min && $weight <= $max) {
                return $additionalCost;
            }
        }
        
        return 0;
    }

    private function calculateDistanceCost($distance, $rules)
    {
        foreach ($rules as $range => $additionalCost) {
            list($min, $max) = explode('-', $range);
            
            if ($distance >= $min && $distance <= $max) {
                return $additionalCost;
            }
        }
        
        return 0;
    }

    private function calculateValueCost($value, $rules)
    {
        foreach ($rules as $range => $additionalCost) {
            list($min, $max) = explode('-', $range);
            
            if ($value >= $min && $value <= $max) {
                return $additionalCost;
            }
        }
        
        return 0;
    }

    public function isAvailableForZone($zoneCode)
    {
        $zones = $this->zones ?? [];
        return empty($zones) || in_array($zoneCode, $zones);
    }

    public function checkLimitations($weight = 0, $dimensions = [])
    {
        $limitations = $this->limitations ?? [];
        
        if (empty($limitations)) {
            return true;
        }
        
        // Check weight limit
        if (isset($limitations['max_weight']) && $weight > $limitations['max_weight']) {
            return false;
        }
        
        // Check dimensions if provided
        if (isset($limitations['max_dimensions']) && !empty($dimensions)) {
            $max = $limitations['max_dimensions'];
            if (isset($dimensions['length']) && $dimensions['length'] > $max['length'] ||
                isset($dimensions['width']) && $dimensions['width'] > $max['width'] ||
                isset($dimensions['height']) && $dimensions['height'] > $max['height']) {
                return false;
            }
        }
        
        return true;
    }

    public function getFormattedCost($weight = 0, $distance = 0, $value = 0)
    {
        $cost = $this->calculateCost($weight, $distance, $value);
        return 'Rp ' . number_format($cost, 0, ',', '.');
    }

    public static function getAvailableMethods($zoneCode = null, $codRequired = false, $weight = 0)
    {
        $query = self::active()->sorted();
        
        if ($zoneCode) {
            $query->where(function($q) use ($zoneCode) {
                $q->whereNull('zones')
                  ->orWhereJsonContains('zones', $zoneCode);
            });
        }
        
        if ($codRequired) {
            $query->where('is_cod_available', true);
        }
        
        return $query->get()->filter(function($method) use ($weight) {
            return $method->checkLimitations($weight);
        });
    }
}