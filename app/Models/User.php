<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'type',
        'employee_id',
        'department',
        'position',
        'hire_date',
        'employment_status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'is_active',
        'is_verified',
        'last_login_at',
        'last_login_ip'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hire_date' => 'date',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    // ============ RELATIONSHIPS ============
    
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ============ STAFF RELATIONSHIPS ============
    
    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function tasks()
    {
        return $this->hasMany(StaffTask::class, 'assigned_to');
    }

    public function assignedTasks()
    {
        return $this->hasMany(StaffTask::class, 'assigned_by');
    }

    public function salary()
    {
        return $this->hasOne(StaffSalary::class);
    }

    public function salarySlips()
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(StaffLeaveRequest::class);
    }

    public function schedules()
    {
        return $this->hasMany(StaffSchedule::class);
    }

    // ============ SCOPES ============
    
    public function scopeCustomers($query)
    {
        return $query->where('type', 'customer');
    }

    public function scopeStaff($query)
    {
        return $query->where('type', 'staff');
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('type', ['admin', 'super_admin']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    // ============ ACCESSORS & MUTATORS ============
    
    public function getFullNameAttribute()
    {
        return $this->name;
    }

    public function getInitialsAttribute()
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2);
    }

    public function getIsStaffAttribute()
    {
        return in_array($this->type, ['staff', 'admin', 'super_admin']);
    }

    public function getIsAdminAttribute()
    {
        return in_array($this->type, ['admin', 'super_admin']);
    }

    // ============ HELPERS ============
    
    public function hasPurchasedProduct($productId)
    {
        return $this->orders()
            ->whereHas('items', function($q) use ($productId) {
                $q->whereHas('productSize', function($q2) use ($productId) {
                    $q2->where('product_id', $productId);
                });
            })
            ->whereIn('status', ['delivered', 'completed'])
            ->exists();
    }

    public function getActiveCart()
    {
        return $this->cart()->where('expires_at', '>', now())->first();
    }

    public function getTotalOrdersAttribute()
    {
        return $this->orders()->count();
    }

    public function getTotalSpentAttribute()
    {
        return $this->orders()
            ->where('payment_status', 'paid')
            ->sum('total_amount');
    }
}