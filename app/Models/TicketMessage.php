<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachments',
        'is_internal',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];

    // RELATIONSHIPS
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // SCOPES
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeExternal($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeByTicket($query, $ticketId)
    {
        return $query->where('ticket_id', $ticketId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ACCESSORS
    public function getAttachmentsUrlsAttribute()
    {
        if (empty($this->attachments)) {
            return [];
        }
        
        return array_map(function($attachment) {
            return asset('storage/' . $attachment);
        }, $this->attachments);
    }

    public function getMessageExcerptAttribute()
    {
        return \Str::limit(strip_tags($this->message), 100);
    }

    public function getIsCustomerMessageAttribute()
    {
        return $this->user_id == $this->ticket->user_id;
    }

    public function getIsStaffMessageAttribute()
    {
        return $this->user_id != $this->ticket->user_id;
    }

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->translatedFormat('d F Y H:i');
    }

    // HELPERS
    public function addAttachment($file)
    {
        $attachments = $this->attachments ?? [];
        $path = $file->store('ticket-attachments', 'public');
        $attachments[] = $path;
        
        $this->update(['attachments' => $attachments]);
        return $this;
    }

    public function removeAttachment($index)
    {
        $attachments = $this->attachments ?? [];
        
        if (isset($attachments[$index])) {
            \Storage::delete('public/' . $attachments[$index]);
            unset($attachments[$index]);
            
            $this->update(['attachments' => array_values($attachments)]);
        }
        
        return $this;
    }

    public function markAsInternal()
    {
        $this->update(['is_internal' => true]);
        return $this;
    }

    public function markAsExternal()
    {
        $this->update(['is_internal' => false]);
        return $this;
    }

    public function getAttachmentCount()
    {
        return count($this->attachments ?? []);
    }

    public function hasAttachments()
    {
        return !empty($this->attachments);
    }

    public function getAttachmentNames()
    {
        if (empty($this->attachments)) {
            return [];
        }
        
        return array_map(function($path) {
            return basename($path);
        }, $this->attachments);
    }

    public function isVisibleToCustomer()
    {
        return !$this->is_internal;
    }

    public function isVisibleToStaff()
    {
        return true; // Staff can see all messages
    }

    public function canDelete($userId)
    {
        // Only allow message deletion within a short time frame
        $timeLimit = now()->subMinutes(30);
        
        return $this->user_id == $userId && 
               $this->created_at > $timeLimit &&
               $this->ticket->status !== 'closed';
    }

    public static function addMessageToTicket($ticketId, $userId, $message, $attachments = [], $isInternal = false)
    {
        $ticketMessage = self::create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $message,
            'attachments' => $attachments,
            'is_internal' => $isInternal,
        ]);
        
        // Update ticket's updated_at
        $ticket = Ticket::find($ticketId);
        if ($ticket) {
            $ticket->touch();
        }
        
        return $ticketMessage;
    }
}