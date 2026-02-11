<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreReviewRequest;
use App\Http\Requests\Api\Product\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of product reviews.
     *
     * @param int $productId
     * @param Request $request
     * @return JsonResponse
     */
    public function index($productId, Request $request): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $query = $product->reviews()
            ->with('user')
            ->where('is_approved', true);

        // Filter by rating
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter with images
        if ($request->boolean('with_images')) {
            $query->whereNotNull('images');
        }

        $reviews = $query->latest()->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'average_rating' => $product->reviews()->where('is_approved', true)->avg('rating'),
                'total_reviews' => $product->reviews()->where('is_approved', true)->count()
            ]
        ]);
    }

    /**
     * Store a newly created review.
     *
     * @param StoreReviewRequest $request
     * @return JsonResponse
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);

        // Check if user has purchased this product
        if (!$request->user()->hasPurchasedProduct($product->id)) {
            return response()->json([
                'message' => 'You can only review products you have purchased',
                'success' => false
            ], 403);
        }

        // Check if user already reviewed this product
        $existing = $product->reviews()
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reviewed this product',
                'success' => false
            ], 422);
        }

        $review = $product->reviews()->create([
            'user_id' => $request->user()->id,
            'rating' => $request->rating,
            'title' => $request->title,
            'content' => $request->content,
            'pros' => $request->pros,
            'cons' => $request->cons,
            'images' => $request->images,
            'is_approved' => false, // Requires admin approval
        ]);

        return response()->json([
            'message' => 'Review submitted successfully and waiting for approval',
            'success' => true,
            'data' => new ReviewResource($review->load('user'))
        ], 201);
    }

    /**
     * Update the specified review.
     *
     * @param UpdateReviewRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateReviewRequest $request, $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        // Only owner can update
        if ($review->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
                'success' => false
            ], 403);
        }

        $review->update([
            'rating' => $request->rating ?? $review->rating,
            'title' => $request->title ?? $review->title,
            'content' => $request->content ?? $review->content,
            'pros' => $request->pros ?? $review->pros,
            'cons' => $request->cons ?? $review->cons,
            'images' => $request->images ?? $review->images,
            'is_approved' => false, // Requires re-approval
        ]);

        return response()->json([
            'message' => 'Review updated successfully and waiting for approval',
            'success' => true,
            'data' => new ReviewResource($review->load('user'))
        ]);
    }

    /**
     * Remove the specified review.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        // Only owner or admin can delete
        if ($review->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json([
                'message' => 'Unauthorized',
                'success' => false
            ], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully',
            'success' => true
        ]);
    }
}
