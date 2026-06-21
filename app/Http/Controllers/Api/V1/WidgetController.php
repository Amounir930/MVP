<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class WidgetController extends Controller
{
    /**
     * Get approved reviews for a specific product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request): JsonResponse
    {
        $sallaProductId = $request->query('product_id');

        if (empty($sallaProductId)) {
            return response()->json([
                'error' => 'The product_id query parameter is required.'
            ], 400);
        }

        // Bypassing global tenant scope to locate the product globally
        $product = Product::withoutGlobalScopes()
            ->with(['tenant.subscription'])
            ->where('salla_product_id', (string) $sallaProductId)
            ->first();

        if (empty($product)) {
            return response()->json([
                'error' => 'Product not found.'
            ], 404);
        }

        // Force bind current tenant ID to scope reviews to this specific product merchant
        App::bind('current_tenant_id', fn () => $product->tenant_id);

        $cacheKey = "widget_data_{$sallaProductId}";

        $widgetData = Cache::remember($cacheKey, 300, function () use ($product, $sallaProductId) {
            // Re-bind inside closure for tenant scoping
            App::bind('current_tenant_id', fn () => $product->tenant_id);

            // Fetch approved reviews belonging to orders containing this product
            $reviews = Review::where('status', 'approved')
                ->whereHas('order.products', function ($query) use ($sallaProductId) {
                    $query->where('products.salla_product_id', $sallaProductId);
                })
                ->with(['customer'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate rating statistics
            $count = $reviews->count();
            $average = $count > 0 ? round($reviews->avg('rating'), 1) : 0.0;

            // Map reviews payload
            $reviewsData = $reviews->map(function (Review $review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'media_url' => $review->media_url,
                    'media_type' => $review->media_type,
                    'answers' => $review->answers,
                    'created_at' => $review->created_at->toIso8601String(),
                    'customer' => [
                        'name' => $review->customer ? $review->customer->name : 'عميل متجر',
                        'avatar_url' => $review->customer ? $review->customer->avatar_url : null,
                    ]
                ];
            });

            $tenant = $product->tenant;
            $showWatermark = $tenant ? $tenant->shouldShowWatermark() : true;

            return [
                'product' => [
                    'salla_product_id' => $product->salla_product_id,
                    'name' => $product->name,
                    'image_url' => $product->image_url,
                    'product_url' => $product->product_url,
                ],
                'rating_stats' => [
                    'average' => (float) $average,
                    'count' => $count,
                ],
                'reviews' => $reviewsData,
                'show_watermark' => (bool) $showWatermark,
            ];
        });

        return response()->json($widgetData, 200);
    }
}
