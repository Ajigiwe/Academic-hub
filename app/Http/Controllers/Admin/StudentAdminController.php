<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = strtoupper(trim((string) $request->query('role', '')));

        $query = User::query()
            ->withCount(['orders', 'entitlements'])
            ->when(in_array($role, ['STUDENT', 'ADMIN'], true), fn ($w) => $w->where('role', $role))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('email', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            }))
            ->orderByDesc('created_at');

        return view('admin.students', [
            'students' => $query->paginate(20)->withQueryString(),
            'q' => $q,
            'role' => in_array($role, ['STUDENT', 'ADMIN'], true) ? $role : '',
            'totalCount' => User::count(),
            'studentCount' => User::where('role', 'STUDENT')->count(),
            'adminCount' => User::where('role', 'ADMIN')->count(),
        ]);
    }

    /**
     * Grant administrator access to an existing user by email address.
     */
    public function grantAdmin(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = trim($data['email']);
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

        if (! $user) {
            return back()->with('error', "No user found with email \"{$email}\". The user must register an account first.");
        }

        if ($user->isAdmin()) {
            return back()->with('status', "{$user->email} is already an administrator.");
        }

        if (! $user->isActive()) {
            return back()->with('error', "Cannot grant administrator access to {$user->email} because the account is suspended. Reactivate it first.");
        }

        $user->update(['role' => 'ADMIN']);

        return back()->with('status', "{$user->email} has been granted administrator access.");
    }

    /**
     * Update a user's role (ADMIN <-> STUDENT).
     */
    public function setRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['ADMIN', 'STUDENT'])],
        ]);

        $newRole = $data['role'];

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own administrator access.');
        }

        if ($user->role === $newRole) {
            return back()->with('status', "{$user->email} is already set to {$newRole}.");
        }

        if ($newRole === 'ADMIN' && ! $user->isActive()) {
            return back()->with('error', "Cannot grant administrator access to {$user->email} because the account is suspended. Reactivate it first.");
        }

        if ($newRole === 'STUDENT' && $user->isAdmin()) {
            $adminCount = User::where('role', 'ADMIN')->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Cannot remove the last administrator on the platform.');
            }
        }

        $user->update(['role' => $newRole]);

        $message = $newRole === 'ADMIN'
            ? "{$user->email} has been granted administrator access."
            : "Administrator access was revoked for {$user->email}.";

        return back()->with('status', $message);
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

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->update(['status' => $status]);

        return back()->with('status', ucfirst(strtolower($status === 'ACTIVE' ? 'activated' : 'suspended'))
            .": {$user->email}");
    }
}
