<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Http\Resources\StaffResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Staff::with('user');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('staff_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                    $qu->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }
                );
            });
        }

        $staff = $query->latest()->paginate($request->get('per_page', 10));
        return StaffResource::collection($staff);
    }

    public function stats()
    {
        return response()->json([
            'total_staff' => Staff::count(),
            'active_staff' => Staff::where('status', 'active')->count(),
            'new_this_month' => Staff::where('created_at', '>=', now()->startOfMonth())->count(),
            'active_rate' => Staff::count() > 0 ? round((Staff::where('status', 'active')->count() / Staff::count()) * 100, 1) : 0
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'staff_number' => 'required|string|unique:staff,staff_number',
            'join_date' => 'required|date',
            'schedule' => 'nullable|string',
            'shift_days' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff',
                'phone' => $request->phone,
                'is_active' => true,
            ]);

            $staff = Staff::create([
                'user_id' => $user->id,
                'staff_number' => $request->staff_number,
                'status' => 'active',
                'join_date' => $request->join_date,
                'schedule' => $request->schedule,
                'shift_days' => $request->shift_days,
                'address' => $request->address,
                'emergency_contact' => $request->emergency_contact,
            ]);

            return new StaffResource($staff);
        });
    }

    public function show(Staff $staff)
    {
        $staff->load('user');
        return new StaffResource($staff);
    }

    public function update(Request $request, Staff $staff)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $staff->user_id,
            'phone' => 'nullable|string',
            'staff_number' => 'required|string|unique:staff,staff_number,' . $staff->id,
            'join_date' => 'required|date',
            'schedule' => 'nullable|string',
            'shift_days' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $staff) {
            $staff->user->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);

            $staff->update([
                'staff_number' => $request->staff_number,
                'join_date' => $request->join_date,
                'schedule' => $request->schedule,
                'shift_days' => $request->shift_days,
                'address' => $request->address,
                'emergency_contact' => $request->emergency_contact,
            ]);
        });

        return new StaffResource($staff->fresh('user'));
    }

    public function updateStatus(Request $request, Staff $staff)
    {
        $request->validate([
            'status' => 'required|in:active,onleave,inactive'
        ]);

        $staff->update(['status' => $request->status]);

        // Sync with user's is_active for simplicity
        $staff->user->update(['is_active' => $request->status === 'active']);

        return new StaffResource($staff);
    }

    public function destroy(Staff $staff)
    {
        $user = $staff->user;
        $staff->delete();
        $user->delete();

        return response()->json(['message' => 'Staff and associated user deleted successfully']);
    }
}
