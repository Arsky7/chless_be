<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'icon',
        'is_read',
        'data',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // SCOPES
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ACCESSORS
    public function getIconClassAttribute()
    {
        $icons = [
            'info' => 'fas fa-info-circle text-blue-500',
            'success' => 'fas fa-check-circle text-green-500',
            'warning' => 'fas fa-exclamation-triangle text-yellow-500',
            'error' => 'fas fa-times-circle text-red-500',
            'system' => 'fas fa-cog text-gray-500',
        ];
        
        return $this->icon ?? ($icons[$this->type] ?? 'fas fa-bell text-gray-500');
    }

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getUrlAttribute()
    {
        $data = $this->data ?? [];
        return $data['url'] ?? null;
    }

    public function getActionTextAttribute()
    {
        $data = $this->data ?? [];
        return $data['action_text'] ?? 'View Details';
    }

    // HELPERS
    public function markAsRead()
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
        
        return $this;
    }

    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
        
        return $this;
    }

    public static function send($userId, $title, $message, $type = 'info', $data = null, $icon = null)
    {
        $notification = self::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'icon' => $icon,
            'data' => $data,
            'is_read' => false,
        ]);
        
        // You could also trigger a real-time notification here
        // event(new NotificationSent($notification));
        
        return $notification;
    }

    public static function sendToMultiple($userIds, $title, $message, $type = 'info', $data = null)
    {
        $notifications = [];
        
        foreach ($userIds as $userId) {
            $notifications[] = self::send($userId, $title, $message, $type, $data);
        }
        
        return $notifications;
    }

    public static function sendToAll($title, $message, $type = 'info', $data = null)
    {
        $userIds = User::pluck('id')->toArray();
        return self::sendToMultiple($userIds, $title, $message, $type, $data);
    }

    public static function sendOrderNotification($userId, $order, $type = 'info')
    {
        $title = 'Order Update';
        $message = "Your order #{$order->order_number} has been {$order->status}.";
        
        $data = [
            'url' => route('orders.show', $order->id),
            'action_text' => 'View Order',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ];
        
        return self::send($userId, $title, $message, $type, $data);
    }

    public static function sendSystemNotification($title, $message, $data = null)
    {
        // Send to admins
        $adminIds = User::whereHas('roles', function($q) {
            $q->where('name', 'admin');
        })->pluck('id')->toArray();
        
        return self::sendToMultiple($adminIds, $title, $message, 'system', $data);
    }

    public function getUnreadCount($userId)
    {
        return self::where('user_id', $userId)->unread()->count();
    }

    public function markAllAsRead($userId)
    {
        return self::where('user_id', $userId)
                    ->unread()
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                    ]);
    }

    public function deleteOldNotifications($days = 30)
    {
        $cutoffDate = now()->subDays($days);
        
        return self::where('created_at', '<', $cutoffDate)
                    ->where('is_read', true)
                    ->delete();
    }

    public function getDataValue($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}