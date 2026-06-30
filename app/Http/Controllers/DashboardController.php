<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary()
    {
        $thisWeekSales = abs(InventoryTransaction::where('type', 'sale')
            ->whereBetween('created_at', [now()->subDays(7), now()])
            ->sum('quantity'));

        $lastWeekSales = abs(InventoryTransaction::where('type', 'sale')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->sum('quantity'));

        $thisMonthSales = abs(InventoryTransaction::where('type', 'sale')
            ->whereBetween('created_at', [now()->subMonths(1), now()])
            ->sum('quantity'));

        $lastMonthSales = abs(InventoryTransaction::where('type', 'sale')
            ->whereBetween('created_at', [now()->subMonths(2), now()->subMonths(1)])
            ->sum('quantity'));

        $avgPurchaseQty = InventoryTransaction::where('type', 'purchase')->avg('quantity');
        $avgSaleQty = abs(InventoryTransaction::where('type', 'sale')->avg('quantity'));

        return response()->json([
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'total_suppliers' => Supplier::count(),
            'total_sale_value' => Product::sum(DB::raw('price * current_stock')),
            'total_cost_value' => Product::sum(DB::raw('COALESCE(cost, 0) * current_stock')),
            'low_stock_products' => Product::where('current_stock', '<', 10)->get(),
            'recent_transactions' => InventoryTransaction::with(['product', 'creator'])->latest()->take(5)->get(),
            'units_sold_this_week' => $thisWeekSales,
            'units_sold_last_week' => $lastWeekSales,
            'units_sold_this_month' => $thisMonthSales,
            'units_sold_last_month' => $lastMonthSales,
            'avg_purchase_quantity' => $avgPurchaseQty,
            'avg_sale_quantity' => $avgSaleQty,
        ], 200);
    }
}
