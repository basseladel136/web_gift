<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Analytics (Admin)
 *
 * APIs for sales and customer analytics.
 * All endpoints require authentication and admin role.
 *
 * @authenticated
 */
class AnalyticsController extends Controller
{
    /**
     * Sales dashboard
     *
     * Returns a full sales analytics dashboard including overview,
     * revenue chart, top products, orders by status, and recent orders.
     *
     * @queryParam period string Period filter. Allowed values: today, week, month, year, all. Default: month. Example: month
     *
     * @response 200 {
     *   "period": "month",
     *   "overview": {
     *     "total_revenue": 15000.00,
     *     "total_orders": 42,
     *     "paid_orders": 38,
     *     "total_customers": 25,
     *     "avg_order_value": 394.74
     *   },
     *   "revenue_chart": [
     *     { "label": "2026-04-01", "revenue": 500.00, "orders": 3 }
     *   ],
     *   "top_products": [
     *     {
     *       "product": { "id": 1, "name": "Rolex Submariner", "price": "5000.00" },
     *       "total_sold": 12,
     *       "total_revenue": 60000.00
     *     }
     *   ],
     *   "orders_by_status": [
     *     { "status": "delivered", "count": 20 },
     *     { "status": "pending", "count": 8 }
     *   ],
     *   "recent_orders": []
     * }
     */
    public function sales(Request $request): JsonResponse
    {
        $validated  = $request->validate([
            'period' => ['sometimes', 'string', 'in:today,week,month,year,all'],
        ]);
        $period     = $validated['period'] ?? 'month';
        $dateFilter = $this->getDateFilter($period);

        return response()->json([
            'period'           => $period,
            'overview'         => $this->getOverview($dateFilter),
            'revenue_chart'    => $this->getRevenueChart($period),
            'top_products'     => $this->getTopProducts($dateFilter),
            'orders_by_status' => $this->getOrdersByStatus($dateFilter),
            'recent_orders'    => $this->getRecentOrders(),
        ]);
    }

    /**
     * Customer analytics
     *
     * Returns customer analytics including new customers,
     * top spenders, and most reviewed products.
     *
     * @queryParam period string Period filter. Allowed values: today, week, month, year, all. Default: month. Example: month
     *
     * @response 200 {
     *   "period": "month",
     *   "new_customers": 15,
     *   "top_spenders": [
     *     {
     *       "id": 1,
     *       "name": "Bassel",
     *       "email": "bassel@test.com",
     *       "total_spent": 9500.00,
     *       "orders": 3
     *     }
     *   ],
     *   "most_reviewed_products": [
     *     {
     *       "id": 1,
     *       "name": "Rolex Submariner",
     *       "price": "5000.00",
     *       "reviews_count": 12
     *     }
     *   ]
     * }
     */
    public function customers(Request $request): JsonResponse
    {
        $validated  = $request->validate([
            'period' => ['sometimes', 'string', 'in:today,week,month,year,all'],
        ]);
        $period     = $validated['period'] ?? 'month';
        $dateFilter = $this->getDateFilter($period);

        $newCustomers = User::where('is_admin', false)
            ->when($dateFilter, fn($q) => $q->where('created_at', '>=', $dateFilter))
            ->count();

        $topSpenders = User::where('is_admin', false)
            ->withSum(['orders as total_spent' => fn($q) => $q->where('payment_status', 'paid')], 'total')
            ->withCount('orders')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(fn($user) => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'total_spent' => round($user->total_spent ?? 0, 2),
                'orders'      => $user->orders_count,
            ]);

        $mostViewedProducts = Product::withCount('reviews')
            ->orderByDesc('reviews_count')
            ->limit(10)
            ->get(['id', 'name', 'price', 'image', 'reviews_count']);

        return response()->json([
            'period'                 => $period,
            'new_customers'          => $newCustomers,
            'top_spenders'           => $topSpenders,
            'most_reviewed_products' => $mostViewedProducts,
        ]);
    }

    /**
     * Overview — total revenue, orders, customers, average order value.
     */
    private function getOverview(?string $dateFilter): array
    {
        $ordersQuery    = Order::where('payment_status', 'paid');
        $allOrdersQuery = Order::query();

        if ($dateFilter) {
            $ordersQuery->where('created_at', '>=', $dateFilter);
            $allOrdersQuery->where('created_at', '>=', $dateFilter);
        }

        $totalRevenue   = (clone $ordersQuery)->sum('total');
        $totalOrders    = (clone $allOrdersQuery)->count();
        $paidOrders     = (clone $ordersQuery)->count();
        $totalCustomers = User::where('is_admin', false)
            ->when($dateFilter, fn($q) => $q->where('created_at', '>=', $dateFilter))
            ->count();

        $avgOrderValue = $paidOrders > 0
            ? round($totalRevenue / $paidOrders, 2)
            : 0;

        return [
            'total_revenue'   => round($totalRevenue, 2),
            'total_orders'    => $totalOrders,
            'paid_orders'     => $paidOrders,
            'total_customers' => $totalCustomers,
            'avg_order_value' => $avgOrderValue,
        ];
    }

    /**
     * Revenue chart — grouped by day, week, or month depending on period.
     */
    private function getRevenueChart(string $period): array
    {
        $groupFormat = match($period) {
            'today' => 'HH24',
            'week'  => 'YYYY-MM-DD',
            'month' => 'YYYY-MM-DD',
            'year'  => 'YYYY-MM',
            default => 'YYYY-MM',
        };

        $dateFilter = $this->getDateFilter($period);

        $data = Order::where('payment_status', 'paid')
            ->when($dateFilter, fn($q) => $q->where('created_at', '>=', $dateFilter))
            ->select(
                DB::raw("TO_CHAR(created_at, '{$groupFormat}') as label"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $data->map(fn($row) => [
            'label'   => $row->label,
            'revenue' => round($row->revenue, 2),
            'orders'  => $row->orders,
        ])->toArray();
    }

    /**
     * Top 10 best selling products.
     */
    private function getTopProducts(?string $dateFilter): array
    {
        $data = OrderItem::with('product:id,name,price,image')
            ->when($dateFilter, fn($q) => $q->where('created_at', '>=', $dateFilter))
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(quantity * unit_price) as total_revenue')
            )
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        return $data->map(fn($row) => [
            'product'       => $row->product,
            'total_sold'    => $row->total_sold,
            'total_revenue' => round($row->total_revenue, 2),
        ])->toArray();
    }

    /**
     * Orders grouped by status.
     */
    private function getOrdersByStatus(?string $dateFilter): array
    {
        $data = Order::when($dateFilter, fn($q) => $q->where('created_at', '>=', $dateFilter))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return $data->map(fn($row) => [
            'status' => $row->status,
            'count'  => $row->count,
        ])->toArray();
    }

    /**
     * Last 10 orders with user info.
     */
    private function getRecentOrders(): array
    {
        $orders = Order::with('user:id,name,email')
            ->latest()
            ->limit(10)
            ->get();

        return $orders->map(fn($order) => [
            'id'             => $order->id,
            'user'           => $order->user,
            'total'          => $order->total,
            'status'         => $order->status,
            'payment_status' => $order->payment_status,
            'created_at'     => $order->created_at,
        ])->toArray();
    }

    /**
     * Get date filter based on period.
     */
    private function getDateFilter(string $period): ?string
    {
        return match($period) {
            'today' => now()->startOfDay()->toDateTimeString(),
            'week'  => now()->startOfWeek()->toDateTimeString(),
            'month' => now()->startOfMonth()->toDateTimeString(),
            'year'  => now()->startOfYear()->toDateTimeString(),
            default => null,
        };
    }
}