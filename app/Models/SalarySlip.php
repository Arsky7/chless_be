<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalarySlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'staff_salary_id',
        'month',
        'year',
        'base_salary',
        'overtime_pay',
        'bonus',
        'allowance_total',
        'deduction_total',
        'tax_amount',
        'total_salary',
        'payment_date',
        'payment_status',
        'payment_proof',
        'notes',
        'breakdown'
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'base_salary' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus' => 'decimal:2',
        'allowance_total' => 'decimal:2',
        'deduction_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'payment_date' => 'date',
        'breakdown' => 'array',
    ];

    protected $appends = [
        'formatted_total',
        'month_name'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staffSalary(): BelongsTo
    {
        return $this->belongsTo(StaffSalary::class);
    }

    public function getFormattedTotalAttribute()
    {
        return 'Rp ' . number_format($this->total_salary, 0, ',', '.');
    }

    public function getMonthNameAttribute()
    {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        return $months[$this->month] ?? '-';
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'pending' => 'badge-warning',
            'paid' => 'badge-success',
            'cancelled' => 'badge-danger'
        ][$this->payment_status] ?? 'badge-secondary';
    }
}
