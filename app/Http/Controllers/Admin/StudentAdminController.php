<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class StudentAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return view('admin.students', [
            'students' => User::where('role', 'STUDENT')
                ->withCount(['orders', 'entitlements'])
                ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                    $w->where('email', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                }))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'q' => $q,
        ]);
    }

    /**
     * Port of setUserStatus: ACTIVE <-> SUSPENDED only, never touches
     * other admins. Suspension blocks new logins (checked in AuthController).
     */
    public function setStatus(Request $request, User $user)
    {
        $status = (string) $request->input('status');
        if (! in_array($status, ['ACTIVE', 'SUSPENDED'], true) || $user->isAdmin()) {
            return back()->with('error', 'Invalid status change.');
        }

        $user->update(['status' => $status]);

        return back()->with('status', ucfirst(strtolower($status === 'ACTIVE' ? 'activated' : 'suspended'))
            .": {$user->email}");
    }
}
