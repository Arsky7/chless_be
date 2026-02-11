<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    // SCOPES
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // HELPERS
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return self::castValue($setting->value, $setting->type);
    }

    public static function set($key, $value, $group = 'general', $type = 'text', $description = null, $options = null)
    {
        $setting = self::where('key', $key)->first();
        
        if ($setting) {
            $setting->update([
                'value' => $value,
                'group' => $group,
                'type' => $type,
                'description' => $description,
                'options' => $options,
            ]);
        } else {
            $setting = self::create([
                'key' => $key,
                'value' => $value,
                'group' => $group,
                'type' => $type,
                'description' => $description,
                'options' => $options,
            ]);
        }
        
        return $setting;
    }

    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
            case 'checkbox':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
            case 'integer':
                return (int) $value;
            case 'decimal':
            case 'float':
                return (float) $value;
            case 'json':
            case 'array':
                return json_decode($value, true);
            case 'text':
            case 'textarea':
            case 'email':
            case 'url':
            default:
                return $value;
        }
    }

    public static function getGroup($group)
    {
        $settings = self::where('group', $group)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = self::castValue($setting->value, $setting->type);
        }
        
        return $result;
    }

    public static function setGroup($group, $data)
    {
        foreach ($data as $key => $value) {
            self::set($key, $value, $group);
        }
        
        return true;
    }

    public static function getAppSettings()
    {
        return self::getGroup('app');
    }

    public static function getMailSettings()
    {
        return self::getGroup('mail');
    }

    public static function getPaymentSettings()
    {
        return self::getGroup('payment');
    }

    public static function getShippingSettings()
    {
        return self::getGroup('shipping');
    }

    public static function getSocialSettings()
    {
        return self::getGroup('social');
    }

    public static function getSeoSettings()
    {
        return self::getGroup('seo');
    }

    public function getFormattedValue()
    {
        switch ($this->type) {
            case 'boolean':
                return $this->value ? 'Yes' : 'No';
            case 'json':
            case 'array':
                return json_encode($this->value, JSON_PRETTY_PRINT);
            default:
                return $this->value;
        }
    }

    public function getOptionsArray()
    {
        if (empty($this->options)) {
            return [];
        }
        
        if (is_string($this->options)) {
            return json_decode($this->options, true);
        }
        
        return $this->options;
    }

    public static function clearCache()
    {
        \Cache::forget('settings');
        return true;
    }

    public static function getAllCached()
    {
        return \Cache::rememberForever('settings', function () {
            $settings = self::all();
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting->key] = self::castValue($setting->value, $setting->type);
            }
            
            return $result;
        });
    }

    public static function flushCache()
    {
        self::clearCache();
        self::getAllCached(); // Rebuild cache
    }
}