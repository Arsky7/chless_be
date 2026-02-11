<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'is_weekend',
        'is_holiday',
        'effective_date',
        'end_date',
        'notes'
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_weekend' => 'boolean',
        'is_holiday' => 'boolean',
    ];

    protected $appends = [
        'working_hours',
        'day_name'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getWorkingHoursAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        return $end->diffInHours($start);
    }

    public function getDayNameAttribute()
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return $days[$this->day_of_week] ?? '-';
    }

    public function getFormattedStartTimeAttribute()
    {
        return \Carbon\Carbon::parse($this->start_time)->format('H:i');
    }

    public function getFormattedEndTimeAttribute()
    {
        return \Carbon\Carbon::parse($this->end_time)->format('H:i');
    }
}
