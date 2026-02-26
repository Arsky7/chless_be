<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReturnRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::with(['order', 'user', 'product'])->latest('request_date');

        if ($request->has('status') && $request->status !== 'All Status') {
            $query->where('status', $request->status);
        }

        if ($request->has('video_status') && $request->video_status !== 'All') {
            $query->where('video_status', $request->video_status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        // Return Date filtering placeholder
        if ($request->has('date_range') && $request->date_range !== 'All Time') {
            // Logic for Today, This Week, This Month
        }

        $returns = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'status' => 'success',
            'data' => $returns
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'reason' => 'required|string',
            'video_status' => 'nullable|string',
            'refund_amount' => 'nullable|numeric'
        ]);

        $validated['return_number'] = 'RET-' . str_pad(ReturnRequest::max('id') + 1, 4, '0', STR_PAD_LEFT);
        $validated['status'] = 'Pending Review';
        $validated['request_date'] = now();

        $returnRequest = ReturnRequest::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Return request created successfully',
            'data' => $returnRequest
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnRequest $returnRequest): JsonResponse
    {
        $returnRequest->load(['order', 'user', 'product']);
        return response()->json([
            'status' => 'success',
            'data' => $returnRequest
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:Pending Review,In Review,Approved,Rejected,Refunded',
            'video_status' => 'nullable|string|in:Video Pending,Video Reviewed,No Video',
            'refund_amount' => 'nullable|numeric'
        ]);

        if (isset($validated['status']) && $validated['status'] !== $returnRequest->status) {
            $validated['status_date'] = now();
        }

        $returnRequest->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Return request updated successfully',
            'data' => $returnRequest
        ]);
    }

    /**
     * Bulk update status for return requests.
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:return_requests,id',
            'action' => 'required|string|in:approve,reject'
        ]);

        $status = $validated['action'] === 'approve' ? 'Approved' : 'Rejected';

        ReturnRequest::whereIn('id', $validated['ids'])->update([
            'status' => $status,
            'status_date' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Return requests ' . $validated['action'] . 'd successfully'
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'pending_review' => ReturnRequest::where('status', 'Pending Review')->count(),
            'in_review' => ReturnRequest::where('status', 'In Review')->count(),
            'approved_this_week' => ReturnRequest::where('status', 'Approved')
                ->whereBetween('status_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'total_refunded' => ReturnRequest::where('status', 'Refunded')->sum('refund_amount')
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}
