<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        return view('admin.orders', [
            'orders' => Order::with(['user', 'items.bundle.course', 'payments', 'refunds'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                    $w->where('reference', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"));
                }))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'q' => $q,
        ]);
    }
}
