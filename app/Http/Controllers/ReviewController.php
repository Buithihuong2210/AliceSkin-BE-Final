<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // Store a new review and update product rating
    public function store(Request $request, $order_id)
    {
        $request->validate([
            'product_reviews' => 'required|array',
            'product_reviews.*.content' => 'required|string',
            'product_reviews.*.rate' => 'required|integer|between:1,5',
        ]);

        $user_id = Auth::id();

        $order = Order::find($order_id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status !== 'Completed') {
            return response()->json(['message' => 'You can only review products for Completed orders.'], 403);
        }

        $orderItems = $order->orderItems;

        if (count($orderItems) !== count($request->product_reviews)) {
            return response()->json(['message' => 'Number of reviews does not match the number of products in the order.'], 400);
        }

        $existingReviews = Review::where('order_id', $order_id)->where('user_id', $user_id)->count();

        if ($existingReviews > 0) {
            return response()->json(['message' => 'You have already reviewed this order.'], 403);
        }

        foreach ($request->product_reviews as $index => $reviewData) {
            $orderItem = $orderItems[$index];

            try {
                Review::create([
                    'content' => $reviewData['content'],
                    'rate' => $reviewData['rate'],
                    'user_id' => $user_id,
                    'product_id' => $orderItem->product_id,
                    'order_id' => $order_id,
                ]);
                $this->updateProductRating($orderItem->product_id);

            } catch (\Exception $e) {
                return response()->json(['message' => 'Failed to create review: ' . $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Reviews created successfully.'], 201);
    }
    private function updateProductRating($product_id)
    {
        $ratings = Review::where('product_id', $product_id)->pluck('rate');
        $averageRating = $ratings->avg();
        Product::where('product_id', $product_id)->update(['rating' => round($averageRating, 2)]);
    }

    public function update(Request $request, $order_id, $review_id)
    {
        $request->validate([
            'content' => 'required|string',
            'rate' => 'required|integer|between:1,5',
        ]);

        $user_id = Auth::id();

        try {
            $review = Review::where('review_id', $review_id)
                ->where('user_id', $user_id)
                ->where('order_id', $order_id)
                ->first();

            if (!$review) {
                return response()->json(['message' => 'Review not found for this user and order.'], 404);
            }

            $review->content = $request->input('content');
            $review->rate = $request->input('rate');

            $review->save();

            return response()->json(['message' => 'Review updated successfully.'], 200);

        } catch (\Exception $e) {
            \Log::error('Error updating review: ' . $e->getMessage());

            return response()->json(['message' => 'An error occurred while trying to update the review.'], 500);
        }
    }

    // Delete a review and recalculate product rating
    public function destroy($order_id, $review_id)
    {
        $user_id = Auth::id();

        try {
            $review = Review::where('review_id', $review_id)
                ->where('user_id', $user_id)
                ->where('order_id', $order_id)
                ->first();

            if (!$review) {
                return response()->json(['message' => 'Review not found for this user and order.'], 404);
            }

            $review->delete();

            return response()->json(['message' => 'Review deleted successfully.'], 200);

        } catch (\Exception $e) {
            \Log::error('Error deleting review: ' . $e->getMessage());

            return response()->json(['message' => 'An error occurred while trying to delete the review.'], 500);
        }
    }

    public function countReviewsByProduct($product_id)
    {
        try {
            $product = Product::find($product_id);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            $totalReviews = Review::where('product_id', $product_id)->count();

            return response()->json([
                'product_id' => $product_id,
                'total_reviews' => $totalReviews
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to count reviews', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserReviews()
    {
        try {
            $user_id = Auth::id();

            if (!$user_id) {
                return response()->json(['message' => 'User not authenticated.'], 401);
            }

            $reviews = Review::with(['user:id,name,image', 'product:product_id,name,image'])
            ->where('user_id', $user_id)
                ->get();

            if ($reviews->isEmpty()) {
                return response()->json(['message' => 'No reviews found for this user.'], 404);
            }

            return response()->json($reviews, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve user reviews', 'error' => $e->getMessage()], 500);
        }
    }

}