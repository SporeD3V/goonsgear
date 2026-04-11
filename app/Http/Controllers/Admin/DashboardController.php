<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $revenue = Order::where('payment_status', 'paid')->sum('total');

        $lowStockVariants = ProductVariant::where('stock', '>', 0)
            ->where('stock', '<=', 5)
            ->count();

        $outOfStockVariants = ProductVariant::where('stock', 0)->count();

        $recentOrders = Order::latest('placed_at')
            ->take(10)
            ->get();

        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();

        return view('admin.dashboard', compact(
            'totalOrders',
            'pendingOrders',
            'revenue',
            'lowStockVariants',
            'outOfStockVariants',
            'recentOrders',
            'totalProducts',
            'activeProducts',
        ));
    }
}
