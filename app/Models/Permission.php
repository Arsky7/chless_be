<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'group',
        'description',
    ];

    // SCOPES
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeByGuard($query, $guard)
    {
        return $query->where('guard_name', $guard);
    }

    // ACCESSORS
    public function getGroupLabelAttribute()
    {
        $groups = [
            'user' => 'User Management',
            'product' => 'Product Management',
            'order' => 'Order Management',
            'content' => 'Content Management',
            'settings' => 'System Settings',
            'reports' => 'Reports & Analytics',
            'permissions' => 'Permissions',
        ];
        
        return $groups[$this->group] ?? ucfirst($this->group);
    }

    public function getDisplayNameAttribute()
    {
        return str_replace(['-', '_'], ' ', ucfirst($this->name));
    }

    // HELPERS
    public static function getGroups()
    {
        return self::distinct('group')
                    ->whereNotNull('group')
                    ->orderBy('group')
                    ->pluck('group');
    }

    public static function getPermissionsByGroup()
    {
        $permissions = self::orderBy('group')->orderBy('name')->get();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $group = $permission->group ?: 'other';
            
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            
            $grouped[$group][] = $permission;
        }
        
        return $grouped;
    }

    public static function createPermission($name, $group = null, $description = null, $guard = 'web')
    {
        $permission = self::firstOrCreate(
            ['name' => $name, 'guard_name' => $guard],
            [
                'group' => $group,
                'description' => $description,
            ]
        );
        
        return $permission;
    }

    public static function initializeDefaultPermissions()
    {
        $defaultPermissions = [
            // User Management
            ['name' => 'view users', 'group' => 'user', 'description' => 'View user list'],
            ['name' => 'create users', 'group' => 'user', 'description' => 'Create new users'],
            ['name' => 'edit users', 'group' => 'user', 'description' => 'Edit existing users'],
            ['name' => 'delete users', 'group' => 'user', 'description' => 'Delete users'],
            ['name' => 'manage user roles', 'group' => 'user', 'description' => 'Assign roles to users'],
            
            // Product Management
            ['name' => 'view products', 'group' => 'product', 'description' => 'View product list'],
            ['name' => 'create products', 'group' => 'product', 'description' => 'Create new products'],
            ['name' => 'edit products', 'group' => 'product', 'description' => 'Edit existing products'],
            ['name' => 'delete products', 'group' => 'product', 'description' => 'Delete products'],
            ['name' => 'manage categories', 'group' => 'product', 'description' => 'Manage product categories'],
            ['name' => 'manage brands', 'group' => 'product', 'description' => 'Manage brands'],
            ['name' => 'manage inventory', 'group' => 'product', 'description' => 'Manage product inventory'],
            
            // Order Management
            ['name' => 'view orders', 'group' => 'order', 'description' => 'View order list'],
            ['name' => 'create orders', 'group' => 'order', 'description' => 'Create new orders'],
            ['name' => 'edit orders', 'group' => 'order', 'description' => 'Edit existing orders'],
            ['name' => 'delete orders', 'group' => 'order', 'description' => 'Delete orders'],
            ['name' => 'update order status', 'group' => 'order', 'description' => 'Update order status'],
            ['name' => 'manage shipments', 'group' => 'order', 'description' => 'Manage order shipments'],
            ['name' => 'manage returns', 'group' => 'order', 'description' => 'Manage order returns'],
            ['name' => 'manage payments', 'group' => 'order', 'description' => 'Manage payments'],
            
            // Content Management
            ['name' => 'view content', 'group' => 'content', 'description' => 'View content list'],
            ['name' => 'create content', 'group' => 'content', 'description' => 'Create new content'],
            ['name' => 'edit content', 'group' => 'content', 'description' => 'Edit existing content'],
            ['name' => 'delete content', 'group' => 'content', 'description' => 'Delete content'],
            ['name' => 'manage pages', 'group' => 'content', 'description' => 'Manage static pages'],
            ['name' => 'manage blog', 'group' => 'content', 'description' => 'Manage blog posts'],
            ['name' => 'manage faq', 'group' => 'content', 'description' => 'Manage FAQ'],
            ['name' => 'manage testimonials', 'group' => 'content', 'description' => 'Manage testimonials'],
            
            // System Settings
            ['name' => 'view settings', 'group' => 'settings', 'description' => 'View system settings'],
            ['name' => 'edit settings', 'group' => 'settings', 'description' => 'Edit system settings'],
            ['name' => 'manage shipping', 'group' => 'settings', 'description' => 'Manage shipping methods'],
            ['name' => 'manage discounts', 'group' => 'settings', 'description' => 'Manage discounts'],
            ['name' => 'manage notifications', 'group' => 'settings', 'description' => 'Manage notifications'],
            
            // Reports & Analytics
            ['name' => 'view reports', 'group' => 'reports', 'description' => 'View reports'],
            ['name' => 'view analytics', 'group' => 'reports', 'description' => 'View analytics'],
            ['name' => 'export reports', 'group' => 'reports', 'description' => 'Export reports'],
            
            // Permissions
            ['name' => 'view permissions', 'group' => 'permissions', 'description' => 'View permissions'],
            ['name' => 'manage permissions', 'group' => 'permissions', 'description' => 'Manage permissions'],
            ['name' => 'manage roles', 'group' => 'permissions', 'description' => 'Manage roles'],
        ];
        
        foreach ($defaultPermissions as $permissionData) {
            self::createPermission(
                $permissionData['name'],
                $permissionData['group'],
                $permissionData['description']
            );
        }
        
        return true;
    }

    public function getRolesWithPermission()
    {
        return $this->roles;
    }

    public function getUsersWithPermission()
    {
        return User::permission($this->name)->get();
    }

    public function assignToRole($roleName)
    {
        $role = Role::where('name', $roleName)->first();
        
        if ($role) {
            $role->givePermissionTo($this);
        }
        
        return $this;
    }

    public function removeFromRole($roleName)
    {
        $role = Role::where('name', $roleName)->first();
        
        if ($role) {
            $role->revokePermissionTo($this);
        }
        
        return $this;
    }

    public static function getPermissionUsage()
    {
        $permissions = self::withCount('roles')->get();
        
        return $permissions->map(function($permission) {
            return [
                'name' => $permission->name,
                'group' => $permission->group,
                'description' => $permission->description,
                'role_count' => $permission->roles_count,
            ];
        })->sortByDesc('role_count');
    }
}