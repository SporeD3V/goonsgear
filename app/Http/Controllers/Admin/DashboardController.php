<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardStatsService $stats): View
    {
        $tab = $request->query('tab', 'overview');

        $data = ['tab' => $tab];

        match ($tab) {
            'sales' => $data += [
                'revenueOverTime' => $stats->revenueOverTime(30),
                'ordersByStatus' => $stats->ordersByStatus(),
                'revenueByCountry' => $stats->revenueByCountry(),
                'topProducts' => $stats->topSellingProducts(),
                'aov' => $stats->averageOrderValue(),
                'repeatRate' => $stats->repeatCustomerRate(),
            ],
            'inventory' => $data += [
                'stockHealth' => $stats->stockHealth(),
                'stockAlertDemand' => $stats->stockAlertDemand(),
                'productStatus' => $stats->productStatusBreakdown(),
            ],
            'promotions' => $data += [
                'couponLeaderboard' => $stats->couponLeaderboard(),
                'discountImpact' => $stats->discountMarginImpact(),
                'cartRecovery' => $stats->cartRecoveryFunnel(),
            ],
            'customers' => $data += [
                'customerStats' => $stats->customerStats(),
                'customerGeo' => $stats->customerGeography(),
                'tagFollows' => $stats->tagFollowPopularity(),
            ],
            default => $data += [
                'overview' => $stats->overviewStats(),
                'recentOrders' => $stats->recentOrders(),
                'revenueOverTime' => $stats->revenueOverTime(30),
                'ordersByStatus' => $stats->ordersByStatus(),
            ],
        };

        return view('admin.dashboard', $data);
    }
}
