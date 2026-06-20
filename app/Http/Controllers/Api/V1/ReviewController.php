<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\WhatsappMessageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews and products with reviews for moderation.
     * Also returns platform metrics for the merchant dashboard.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        // Fetch all reviews sorted by created date, including customers and the order's products
        $reviews = Review::with(['customer', 'order.products'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch all products, and attach reviews associated with each product
        $products = Product::all()->map(function (Product $product) {
            $productReviews = Review::whereHas('order.products', function ($query) use ($product) {
                $query->where('products.id', $product->id);
            })
            ->with(['customer', 'order.products'])
            ->orderBy('created_at', 'desc')
            ->get();

            // Set dynamic attribute for frontend rendering
            $product->setAttribute('reviews', $productReviews);
            $product->setAttribute('reviews_count', $productReviews->count());
            $product->setAttribute('reviews_avg_rating', $productReviews->count() > 0 ? round($productReviews->avg('rating'), 1) : 0.0);

            return $product;
        });

        // Compute merchant dashboard statistics
        $avgRating = $reviews->count() > 0 ? round($reviews->avg('rating'), 1) : 0.0;
        $totalReviews = $reviews->count();

        // WhatsApp message logs statistics
        $totalMessagesThisMonth = WhatsappMessageLog::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $deliveredMessagesThisMonth = WhatsappMessageLog::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'sent')
            ->count();

        $deliverySuccessRate = $totalMessagesThisMonth > 0 
            ? round(($deliveredMessagesThisMonth / $totalMessagesThisMonth) * 100, 1) 
            : 100.0;

        // Daily review count for the last 30 days (dynamic chart data)
        $last30DaysReviews = Review::where('created_at', '>=', now()->subDays(30))
            ->get()
            ->groupBy(function ($review) {
                return $review->created_at->format('Y-m-d');
            });

        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartData[] = [
                'date' => $date,
                'count' => isset($last30DaysReviews[$date]) ? $last30DaysReviews[$date]->count() : 0,
            ];
        }

        return response()->json([
            'reviews' => $reviews,
            'products' => $products,
            'stats' => [
                'avg_rating' => $avgRating,
                'total_reviews' => $totalReviews,
                'messages_consumed' => $totalMessagesThisMonth,
                'delivery_rate' => $deliverySuccessRate,
                'chart_data' => $chartData,
            ]
        ], 200);
    }

    /**
     * Update the moderation status of a review.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review->update([
            'status' => $validated['status'],
        ]);

        // Clear storefront widget cache for associated products
        if ($review->order && $review->order->products) {
            foreach ($review->order->products as $product) {
                Cache::forget("widget_data_{$product->salla_product_id}");
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة التقييم بنجاح.',
            'review' => $review->load(['customer', 'order.products']),
        ], 200);
    }

    /**
     * Submit or update official merchant reply to a review.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function reply(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'reply' => 'nullable|string|max:5000',
        ]);

        // Clean HTML tags for basic sanitization
        $reply = $validated['reply'] ? strip_tags($validated['reply']) : null;

        $review->update([
            'reply' => $reply,
            'replied_at' => $reply ? now() : null,
        ]);

        // Clear storefront widget cache for associated products
        if ($review->order && $review->order->products) {
            foreach ($review->order->products as $product) {
                Cache::forget("widget_data_{$product->salla_product_id}");
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الرد بنجاح.',
            'review' => $review->load(['customer', 'order.products']),
        ], 200);
    }

    /**
     * Export reviews as formatted CSV file with RTL support.
     */
    public function export(Request $request)
    {
        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=reviews-export-' . now()->format('Y-m-d') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $reviews = Review::with(['customer', 'order.products'])
            ->orderBy('created_at', 'desc')
            ->get();

        $callback = function () use ($reviews) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for correct Arabic encoding in Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Headers
            fputcsv($file, [
                'الاسم',
                'رقم الهاتف',
                'رقم الفاتورة',
                'تاريخ الطلب',
                'التقييم',
                'التعليق',
                'الرد الرسمي للمتجر',
                'الحالة',
                'المصدر',
                'إجابات الأسئلة المخصصة'
            ]);

            foreach ($reviews as $review) {
                $customAnswersStr = '';
                if ($review->answers && is_array($review->answers)) {
                    $ansList = [];
                    foreach ($review->answers as $ans) {
                        if (isset($ans['text']) && isset($ans['response'])) {
                            $ansList[] = $ans['text'] . ': ' . $ans['response'];
                        }
                    }
                    $customAnswersStr = implode(' | ', $ansList);
                }

                fputcsv($file, [
                    $review->customer ? $review->customer->name : 'عميل مجهول',
                    $review->customer ? $review->customer->phone : 'N/A',
                    $review->order ? $review->order->invoice_number : 'N/A',
                    $review->created_at->format('Y-m-d H:i'),
                    $review->rating . ' نجوم',
                    $review->comment ?? '',
                    $review->reply ?? '',
                    $review->status === 'approved' ? 'مقبول' : ($review->status === 'rejected' ? 'مرفوض' : 'قيد المراجعة'),
                    $review->source ?? 'واتساب',
                    $customAnswersStr
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Delete a review.
     *
     * @param  \App\Models\Review  $review
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Review $review): JsonResponse
    {
        // Clear storefront widget cache for associated products before deletion
        if ($review->order && $review->order->products) {
            foreach ($review->order->products as $product) {
                Cache::forget("widget_data_{$product->salla_product_id}");
            }
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التقييم بنجاح.',
        ], 200);
    }
}
