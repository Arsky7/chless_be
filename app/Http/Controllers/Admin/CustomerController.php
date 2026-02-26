<?php
// app/Http/Controllers/Admin/CustomerController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * GET /admin/customers/stats
     */
    public function stats()
    {
        try {
            $query = User::where('role', 'customer');

            $total    = (clone $query)->count();
            $active   = (clone $query)->where('is_active', true)->count();
            $inactive = (clone $query)->where('is_active', false)->count();

            // New = Registered within last 30 days
            $newCustomers = (clone $query)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            // Loyal = 5+ orders
            $loyal = (clone $query)
                ->whereHas('orders', fn($q) => $q, '>=', 5)
                ->count();

            return response()->json([
                'success' => true,
                'data'    => [
                    'total'          => $total,
                    'active'         => $active,
                    'inactive'       => $inactive,
                    'new_this_month' => $newCustomers,
                    'loyal'          => $loyal,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /admin/customers
     * Paginated, filterable list of customers.
     */
    public function index(Request $request)
    {
        try {
            $search   = $request->get('search', '');
            $type     = $request->get('type', '');    // new|loyal|inactive|active
            $perPage  = (int) $request->get('per_page', 15);
            $page     = (int) $request->get('page', 1);

            $query = User::where('role', 'customer')
                ->withCount('orders')
                ->withSum('orders', 'total')
                ->withMax('orders as last_order_at', 'created_at');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Type filter (derived: loyal ≥5 orders, new ≤30 days, inactive = is_active false)
            if ($type === 'loyal') {
                $query->whereHas('orders', fn($q) => $q, '>=', 5);
            } elseif ($type === 'new') {
                $query->where('created_at', '>=', now()->subDays(30));
            } elseif ($type === 'inactive') {
                $query->where('is_active', false);
            } elseif ($type === 'active') {
                $query->where('is_active', true);
            }

            $paginated = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginated->items())->map(function ($u) {
                $orderCount  = $u->orders_count ?? 0;
                $totalSpent  = $u->orders_sum_total ?? 0;
                $lastOrder   = $u->last_order_at;

                // Determine customer type label
                if (!$u->is_active) {
                    $customerType = 'inactive';
                } elseif ($orderCount >= 5) {
                    $customerType = 'loyal';
                } elseif ($u->created_at >= now()->subDays(30)) {
                    $customerType = 'new';
                } else {
                    $customerType = 'active';
                }

                return [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'email'        => $u->email,
                    'phone'        => $u->phone,
                    'address'      => $u->address,
                    'is_active'    => $u->is_active,
                    'avatar_url'   => $u->avatar_url,
                    'initials'     => strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', trim($u->name)))))),
                    'customer_type'=> $customerType,
                    'order_count'  => $orderCount,
                    'total_spent'  => (float) $totalSpent,
                    'last_order_at'=> $lastOrder ? \Carbon\Carbon::parse($lastOrder)->toDateString() : null,
                    'joined_at'    => $u->created_at?->toDateString(),
                ];
            });

            return response()->json([
                'success'      => true,
                'data'         => $items,
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /admin/customers/{user}
     */
    public function show(User $customer)
    {
        try {
            $customer->loadCount('orders');
            $customer->loadSum('orders', 'total');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'          => $customer->id,
                    'name'        => $customer->name,
                    'email'       => $customer->email,
                    'phone'       => $customer->phone,
                    'address'     => $customer->address,
                    'is_active'   => $customer->is_active,
                    'avatar_url'  => $customer->avatar_url,
                    'order_count' => $customer->orders_count,
                    'total_spent' => (float) ($customer->orders_sum_total ?? 0),
                    'joined_at'   => $customer->created_at?->toDateString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /admin/customers
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'phone'    => 'nullable|string|max:20',
                'address'  => 'nullable|string',
            ]);

            $customer = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => bcrypt($validated['password']),
                'phone'     => $validated['phone'] ?? null,
                'address'   => $validated['address'] ?? null,
                'role'      => 'customer',
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data'    => $customer
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * PUT /admin/customers/{customer}
     */
    public function update(Request $request, User $customer)
    {
        try {
            $validated = $request->validate([
                'name'    => 'required|string|max:255',
                'email'   => 'required|email|unique:users,email,' . $customer->id,
                'phone'   => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            if ($request->has('password') && !empty($request->password)) {
                $validated['password'] = bcrypt($request->password);
            }

            $customer->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data'    => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * DELETE /admin/customers/{customer}
     */
    public function destroy(User $customer)
    {
        try {
            $customer->delete();
            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PATCH /admin/customers/{customer}/toggle-active
     */
    public function toggleActive(User $customer)
    {
        try {
            $customer->update(['is_active' => !$customer->is_active]);
            return response()->json([
                'success'   => true,
                'is_active' => $customer->is_active,
                'message'   => $customer->is_active ? 'Customer activated' : 'Customer deactivated',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
