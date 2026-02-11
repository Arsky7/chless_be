<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'message',
        'context',
        'channel',
        'extra',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
        'extra' => 'array',
    ];

    // SCOPES
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeError($query)
    {
        return $query->whereIn('level', ['error', 'critical']);
    }

    public function scopeWarning($query)
    {
        return $query->where('level', 'warning');
    }

    public function scopeInfo($query)
    {
        return $query->where('level', 'info');
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ACCESSORS
    public function getLevelColorAttribute()
    {
        $colors = [
            'debug' => 'gray',
            'info' => 'blue',
            'warning' => 'yellow',
            'error' => 'red',
            'critical' => 'purple',
        ];
        
        return $colors[$this->level] ?? 'gray';
    }

    public function getLevelIconAttribute()
    {
        $icons = [
            'debug' => 'fas fa-bug',
            'info' => 'fas fa-info-circle',
            'warning' => 'fas fa-exclamation-triangle',
            'error' => 'fas fa-times-circle',
            'critical' => 'fas fa-skull-crossbones',
        ];
        
        return $icons[$this->level] ?? 'fas fa-circle';
    }

    public function getContextFormattedAttribute()
    {
        if (empty($this->context)) {
            return null;
        }
        
        return json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    // HELPERS
    public static function log($level, $message, $context = [], $channel = 'application')
    {
        $request = request();
        
        $log = self::create([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'channel' => $channel,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return $log;
    }

    public static function info($message, $context = [], $channel = 'application')
    {
        return self::log('info', $message, $context, $channel);
    }

    public static function warning($message, $context = [], $channel = 'application')
    {
        return self::log('warning', $message, $context, $channel);
    }

    public static function error($message, $context = [], $channel = 'application')
    {
        return self::log('error', $message, $context, $channel);
    }

    public static function critical($message, $context = [], $channel = 'application')
    {
        return self::log('critical', $message, $context, $channel);
    }

    public static function debug($message, $context = [], $channel = 'application')
    {
        return self::log('debug', $message, $context, $channel);
    }

    public static function audit($action, $model = null, $changes = [], $userId = null)
    {
        $context = [
            'action' => $action,
            'model' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'changes' => $changes,
        ];
        
        return self::log('info', 'Audit: ' . $action, $context, 'audit');
    }

    public static function api($method, $url, $status, $responseTime = null, $userId = null)
    {
        $context = [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'response_time' => $responseTime,
        ];
        
        $level = $status >= 400 ? ($status >= 500 ? 'error' : 'warning') : 'info';
        
        return self::log($level, 'API: ' . $method . ' ' . $url . ' - ' . $status, $context, 'api');
    }

    public static function security($event, $details = [], $userId = null)
    {
        return self::log('warning', 'Security: ' . $event, $details, 'security');
    }

    public static function getStats($period = 'today')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->where('created_at', '>=', now()->subWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->subMonth());
                break;
        }
        
        $total = $query->count();
        $errors = $query->clone()->error()->count();
        $warnings = $query->clone()->warning()->count();
        $infos = $query->clone()->info()->count();
        
        return [
            'total' => $total,
            'errors' => $errors,
            'warnings' => $warnings,
            'infos' => $infos,
            'error_rate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0,
        ];
    }

    public static function getErrorStats($period = 'month')
    {
        $query = self::error()->where('created_at', '>=', now()->subMonth());
        
        return $query->selectRaw('DATE(created_at) as date')
                     ->selectRaw('COUNT(*) as count')
                     ->groupBy('date')
                     ->orderBy('date')
                     ->get()
                     ->map(function($item) {
                         return [
                             'date' => $item->date,
                             'count' => $item->count,
                         ];
                     });
    }

    public static function getTopErrors($limit = 10, $period = 'month')
    {
        $query = self::error()->where('created_at', '>=', now()->subMonth());
        
        return $query->select('message')
                     ->selectRaw('COUNT(*) as count')
                     ->groupBy('message')
                     ->orderBy('count', 'desc')
                     ->limit($limit)
                     ->get();
    }

    public static function cleanOldLogs($days = 30)
    {
        $cutoffDate = now()->subDays($days);
        
        return self::where('created_at', '<', $cutoffDate)
                    ->where('level', '!=', 'error')
                    ->delete();
    }

    public function getUserNameAttribute()
    {
        if ($this->user_id && $user = User::find($this->user_id)) {
            return $user->name;
        }
        
        return 'System';
    }

    public function getContextValue($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }
}