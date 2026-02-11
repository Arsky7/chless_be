<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'is_system',
        'level',
        'permissions',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'permissions' => 'array',
    ];

    // SCOPES
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeHigherThan($query, $level)
    {
        return $query->where('level', '>', $level);
    }

    public function scopeLowerThan($query, $level)
    {
        return $query->where('level', '<', $level);
    }

    // ACCESSORS
    public function getPermissionCountAttribute()
    {
        return $this->permissions()->count();
    }

    public function getUserCountAttribute()
    {
        return $this->users()->count();
    }

    public function getIsAdminAttribute()
    {
        return $this->name === 'admin';
    }

    public function getIsSuperAdminAttribute()
    {
        return $this->name === 'super-admin';
    }

    public function getIsCustomerAttribute()
    {
        return $this->name === 'customer';
    }

    public function getIsStaffAttribute()
    {
        return $this->name === 'staff';
    }

    // HELPERS
    public static function createCustom($name, $description = null, $level = 1, $permissions = [])
    {
        $role = self::create([
            'name' => $name,
            'guard_name' => 'web',
            'description' => $description,
            'is_system' => false,
            'level' => $level,
        ]);
        
        if (!empty($permissions)) {
            $role->syncPermissions($permissions);
        }
        
        return $role;
    }

    public function updatePermissions($permissionNames)
    {
        $permissions = Permission::whereIn('name', $permissionNames)->get();
        $this->syncPermissions($permissions);
        
        return $this;
    }

    public function assignToUser($userId)
    {
        $user = User::find($userId);
        
        if ($user) {
            $user->assignRole($this);
        }
        
        return $this;
    }

    public function removeFromUser($userId)
    {
        $user = User::find($userId);
        
        if ($user) {
            $user->removeRole($this);
        }
        
        return $this;
    }

    public function canAssignTo($assignerRole)
    {
        if (!$assignerRole) {
            return false;
        }
        
        return $assignerRole->level > $this->level;
    }

    public function getPermissionNames()
    {
        return $this->permissions->pluck('name')->toArray();
    }

    public function hasPermission($permissionName)
    {
        return $this->permissions->contains('name', $permissionName);
    }

    public static function getDefaultRoles()
    {
        return [
            [
                'name' => 'super-admin',
                'description' => 'Super Administrator - Full system access',
                'is_system' => true,
                'level' => 10,
                'permissions' => ['*'],
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator - Full management access',
                'is_system' => true,
                'level' => 9,
                'permissions' => [
                    'view dashboard',
                    'manage users',
                    'manage products',
                    'manage orders',
                    'manage content',
                    'manage settings',
                ],
            ],
            [
                'name' => 'manager',
                'description' => 'Manager - Department management',
                'is_system' => true,
                'level' => 8,
                'permissions' => [
                    'view dashboard',
                    'manage products',
                    'manage orders',
                    'view reports',
                ],
            ],
            [
                'name' => 'staff',
                'description' => 'Staff - Limited access',
                'is_system' => true,
                'level' => 5,
                'permissions' => [
                    'view dashboard',
                    'view orders',
                    'update order status',
                    'view products',
                ],
            ],
            [
                'name' => 'customer',
                'description' => 'Customer - Basic user access',
                'is_system' => true,
                'level' => 1,
                'permissions' => [
                    'view products',
                    'place orders',
                    'manage profile',
                ],
            ],
        ];
    }

    public static function initializeDefaultRoles()
    {
        $defaultRoles = self::getDefaultRoles();
        
        foreach ($defaultRoles as $roleData) {
            $role = self::firstOrCreate(
                ['name' => $roleData['name']],
                [
                    'guard_name' => 'web',
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system'],
                    'level' => $roleData['level'],
                ]
            );
            
            if (!empty($roleData['permissions'])) {
                if ($roleData['permissions'][0] === '*') {
                    $role->givePermissionTo(Permission::all());
                } else {
                    $role->syncPermissions($roleData['permissions']);
                }
            }
        }
        
        return true;
    }
}