<?php

namespace App\Http\Controllers;

use App\Models\Favourite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile, account info, and purchase history.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        $orders = $user->orders()
            ->with(['items.bundle.course', 'refunds'])
            ->latest()
            ->paginate(10);

        $papersCount = $user->entitlements()->count();
        $ordersCount = $user->orders()->where('status', 'PAID')->count();
        $totalSpentPesewas = (int) $user->orders()->where('status', 'PAID')->sum('amount_pesewas');
        $favouritesCount = Favourite::where('user_id', $user->id)->count();

        return view('profile.show', [
            'user' => $user,
            'orders' => $orders,
            'papersCount' => $papersCount,
            'ordersCount' => $ordersCount,
            'totalSpentPesewas' => $totalSpentPesewas,
            'favouritesCount' => $favouritesCount,
        ]);
    }

    /**
     * Update the user's basic profile details.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
        ]);

        return back()->with('status', 'Account details updated successfully.');
    }

    /**
     * Upload or update the user's profile photo.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
        ]);

        $user = $request->user();

        // Remove old avatar file if present
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return back()->with('status', 'Profile photo updated successfully.');
    }

    /**
     * Remove the custom profile photo.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return back()->with('status', 'Profile photo removed.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return back()->with('status', 'Password updated successfully.');
    }

    /**
     * Serve the user's avatar image or render a sharp dynamic SVG with their initials.
     */
    public function avatar(User $user): BinaryFileResponse|Response
    {
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            $filePath = Storage::disk('public')->path($user->avatar);
            return response()->file($filePath, [
                'Content-Type' => mime_content_type($filePath) ?: 'image/jpeg',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            ]);
        }

        // Generate dynamic SVG avatar
        $initials = $user->initials ?: 'U';
        $palette = [
            ['#2563eb', '#1d4ed8'],
            ['#0284c7', '#0369a1'],
            ['#7c3aed', '#6d28d9'],
            ['#059669', '#047857'],
            ['#d97706', '#b45309'],
        ];
        $index = abs(crc32($user->email ?: 'academic-hub')) % count($palette);
        [$color1, $color2] = $palette[$index];

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="{$color1}" />
            <stop offset="100%" stop-color="{$color2}" />
        </linearGradient>
    </defs>
    <rect width="128" height="128" rx="64" fill="url(#grad)" />
    <text x="50%" y="54%" font-family="system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" font-size="44" font-weight="700" fill="#ffffff" text-anchor="middle" dominant-baseline="middle" letter-spacing="1">{$initials}</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
        ]);
    }
}
