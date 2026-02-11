<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffSalary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'base_salary',
        'bank_name',
        'bank_account',
        'account_holder',
        'tax_id',
        'daily_rate',
        'overtime_rate',
        'allowances',
        'deductions'
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'allowances' => 'array',
        'deductions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function getFormattedBaseSalaryAttribute()
    {
        return 'Rp ' . number_format($this->base_salary, 0, ',', '.');
    }
}
