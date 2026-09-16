<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Bundle, Order, Payment, Resource, User};

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            // The original's four headline stats.
            'revenuePesewas' => Order::where('status', 'PAID')->sum('amount_pesewas'),
            'paidSales' => Order::where('status', 'PAID')->count(),
            'studentsCount' => User::where('role', 'STUDENT')->count(),
            'publishedBundles' => Bundle::where('status', 'PUBLISHED')->count(),

            // Kept from the Laravel slice (operational detail the
            // original's Payments screen covers).
            'pendingPayments' => Payment::where('status', 'PENDING')->count(),

            // Recent orders with student info — port of the dashboard's
            // "Recent orders · View all" table (8 rows).
            'recentOrders' => Order::with('user')->latest()->limit(8)->get(),

            // Popular bundles by paid sales — port of the "Popular
            // bundles" ranked list.
            'popular' => Bundle::published()
                ->withCount(['orderItems as paid_order_items_count' => fn ($q) => $q
                    ->whereHas('order', fn ($o) => $o->where('status', 'PAID'))])
                ->orderByDesc('paid_order_items_count')
                ->limit(5)
                ->get(),
        ]);
    }
}
