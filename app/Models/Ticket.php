<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'assigned_to',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // BOOT
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TICKET-' . date('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

    // RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    // SCOPES
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByAssigned($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ACCESSORS
    public function getStatusLabelAttribute()
    {
        $labels = [
            'open' => 'Terbuka',
            'in_progress' => 'Dalam Proses',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute()
    {
        $labels = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
        ];
        
        return $labels[$this->priority] ?? $this->priority;
    }

    public function getCategoryLabelAttribute()
    {
        $labels = [
            'technical' => 'Teknis',
            'billing' => 'Pembayaran',
            'sales' => 'Penjualan',
            'support' => 'Support',
            'other' => 'Lainnya',
        ];
        
        return $labels[$this->category] ?? $this->category;
    }

    public function getIsOpenAttribute()
    {
        return $this->status === 'open';
    }

    public function getIsClosedAttribute()
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function getIsAssignedAttribute()
    {
        return !is_null($this->assigned_to);
    }

    public function getLastActivityAttribute()
    {
        $lastMessage = $this->messages()->latest()->first();
        
        if ($lastMessage) {
            return $lastMessage->created_at;
        }
        
        return $this->updated_at;
    }

    public function getDaysOpenAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    // HELPERS
    public function assignTo($userId)
    {
        $this->update([
            'assigned_to' => $userId,
            'status' => 'in_progress',
        ]);
        
        return $this;
    }

    public function unassign()
    {
        $this->update([
            'assigned_to' => null,
            'status' => 'open',
        ]);
        
        return $this;
    }

    public function markAsInProgress()
    {
        $this->update(['status' => 'in_progress']);
        return $this;
    }

    public function markAsResolved()
    {
        $this->update([
            'status' => 'resolved',
            'closed_at' => now(),
        ]);
        
        return $this;
    }

    public function markAsClosed()
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        
        return $this;
    }

    public function reopen()
    {
        $this->update([
            'status' => 'open',
            'closed_at' => null,
        ]);
        
        return $this;
    }

    public function addMessage($message, $userId, $attachments = [], $isInternal = false)
    {
        $ticketMessage = TicketMessage::create([
            'ticket_id' => $this->id,
            'user_id' => $userId,
            'message' => $message,
            'attachments' => $attachments,
            'is_internal' => $isInternal,
        ]);
        
        // Update ticket status if it's a customer reply
        if (!$isInternal && $this->status === 'resolved') {
            $this->reopen();
        }
        
        return $ticketMessage;
    }

    public function getCustomerMessages()
    {
        return $this->messages()->where('is_internal', false)->get();
    }

    public function getInternalMessages()
    {
        return $this->messages()->where('is_internal', true)->get();
    }

    public function getUnreadMessagesCount($userId)
    {
        // This would need a read status field in messages
        // For now, return 0
        return 0;
    }

    public static function createTicket($userId, $subject, $description, $category = 'support', $priority = 'medium')
    {
        $ticket = self::create([
            'user_id' => $userId,
            'subject' => $subject,
            'description' => $description,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
        ]);
        
        // Add initial message
        $ticket->addMessage($description, $userId);
        
        return $ticket;
    }

    public function getResponseTime()
    {
        $firstResponse = $this->messages()
                              ->where('user_id', '!=', $this->user_id)
                              ->orderBy('created_at')
                              ->first();
        
        if ($firstResponse) {
            return $this->created_at->diffInHours($firstResponse->created_at);
        }
        
        return null;
    }

    public function getResolutionTime()
    {
        if ($this->closed_at) {
            return $this->created_at->diffInHours($this->closed_at);
        }
        
        return null;
    }

    public static function getStats($userId = null, $assignedTo = null)
    {
        $query = self::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }
        
        return [
            'total' => $query->count(),
            'open' => $query->clone()->open()->count(),
            'in_progress' => $query->clone()->inProgress()->count(),
            'resolved' => $query->clone()->resolved()->count(),
            'closed' => $query->clone()->closed()->count(),
            'unassigned' => $query->clone()->unassigned()->count(),
        ];
    }
}