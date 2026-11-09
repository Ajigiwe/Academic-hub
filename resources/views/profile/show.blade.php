@extends('layouts.app')

@section('title', 'My Account — Academic Hub')

@section('content')
<div class="mx-auto max-w-5xl">
    {{-- ── 1. Profile Header / Hero Card ───────────────────────────────── --}}
    <div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-6 shadow-soft dark:border-neutral-800 dark:bg-neutral-900 sm:p-8">
        {{-- Subtle background decoration --}}
        <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-brand-50/70 blur-3xl dark:bg-brand-950/40" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                {{-- Avatar display with instant photo change form --}}
                <div class="relative shrink-0">
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-24 w-24 rounded-full border-2 border-white object-cover shadow-card ring-4 ring-brand-100 dark:border-neutral-900 dark:ring-brand-950">

                    {{-- Upload button overlay --}}
                    <form method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" id="avatar-form" class="absolute -bottom-1 -right-1">
                        @csrf
                        <label for="avatar-input" class="grid h-8 w-8 cursor-pointer place-items-center rounded-full bg-brand-600 text-white shadow-md transition hover:bg-brand-700" title="Upload new photo">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        </label>
                        <input type="file" id="avatar-input" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" onchange="document.getElementById('avatar-form').submit();">
                    </form>
                </div>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-white sm:text-3xl">{{ $user->name }}</h1>
                        @if ($user->isAdmin())
                            <span class="badge-gold font-semibold">Platform Admin</span>
                        @else
                            <span class="badge-brand font-semibold">Student</span>
                        @endif
                        <span class="badge-success font-semibold">{{ $user->status }}</span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
                        <span class="inline-flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            {{ $user->email }}
                        </span>
                        @if ($user->phone)
                            <span class="inline-flex items-center gap-1.5">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                {{ $user->phone }}
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Member since {{ $user->created_at->format('M Y') }}
                        </span>
                    </div>

                    @if ($user->hasCustomAvatar())
                        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-2.5">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-600 transition hover:underline dark:text-red-400">
                                Remove custom photo
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($user->isAdmin())
                <div>
                    <a href="{{ route('admin.dashboard') }}" class="btn-primary btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                        Admin Control Center
                    </a>
                </div>
            @endif
        </div>

        {{-- Stat highlights --}}
        <div class="mt-6 grid grid-cols-2 gap-3 border-t border-neutral-100 pt-6 sm:grid-cols-4 dark:border-neutral-800">
            <a href="{{ route('library') }}" class="rounded-xl border border-neutral-100 p-3.5 transition hover:bg-neutral-50 dark:border-neutral-800/80 dark:hover:bg-neutral-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Unlocked Papers</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-brand-700 dark:text-brand-400">{{ $papersCount }}</p>
            </a>

            <div class="rounded-xl border border-neutral-100 p-3.5 dark:border-neutral-800/80">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Total Purchases</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-neutral-900 dark:text-white">{{ $ordersCount }}</p>
            </div>

            <div class="rounded-xl border border-neutral-100 p-3.5 dark:border-neutral-800/80">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Total Spent</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-neutral-900 dark:text-white">GHS {{ number_format($totalSpentPesewas / 100, 2) }}</p>
            </div>

            <a href="{{ route('favourites.index') }}" class="rounded-xl border border-neutral-100 p-3.5 transition hover:bg-neutral-50 dark:border-neutral-800/80 dark:hover:bg-neutral-800/50">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">Saved Items</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $favouritesCount }}</p>
            </a>
        </div>
    </div>

    {{-- Error validation banner for avatar/account --}}
    @if ($errors->any())
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/60 dark:text-red-300">
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── 2. Two-Column Settings & Password ──────────────────────────── --}}
    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Edit Account Information --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-soft dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-white">Account Information</h2>
                    <p class="text-xs text-neutral-500">Update your name, contact details and email address</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="label">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required class="input">
                    </div>
                    <div>
                        <label for="last_name" class="label">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required class="input">
                    </div>
                </div>

                <div>
                    <label for="email" class="label">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
                </div>

                <div>
                    <label for="phone" class="label">Phone / WhatsApp Number</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="e.g. +233 24 000 0000" class="input">
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- Change Password --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-soft dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-white">Change Password</h2>
                    <p class="text-xs text-neutral-500">Ensure your account uses a strong, unique password</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.password') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="current_password" class="label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required class="input" placeholder="••••••••">
                </div>

                <div>
                    <label for="password" class="label">New Password</label>
                    <input type="password" id="password" name="password" required class="input" placeholder="At least 8 characters">
                </div>

                <div>
                    <label for="password_confirmation" class="label">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required class="input" placeholder="Repeat new password">
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="btn-secondary">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── 3. Purchase & Order History ─────────────────────────────────── --}}
    <div class="mt-8">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-white">Purchase History</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Official order receipts and transactions associated with your account</p>
            </div>
            @if ($orders->isNotEmpty())
                <span class="badge-neutral text-xs tabular-nums">{{ $orders->total() }} order{{ $orders->total() === 1 ? '' : 's' }}</span>
            @endif
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-soft dark:border-neutral-800 dark:bg-neutral-900">
            @if ($orders->isEmpty())
                <div class="p-12 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-neutral-100 text-neutral-400 dark:bg-neutral-800">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                    </span>
                    <p class="mt-4 font-semibold text-neutral-900 dark:text-white">No purchases recorded yet</p>
                    <p class="mt-1 text-xs text-neutral-500">When you buy past questions and study bundles, receipts and access links will show here.</p>
                    <a href="{{ route('courses') }}" class="btn-primary btn-sm mt-5 inline-flex">
                        Browse Past Questions
                    </a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table-responsive w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-neutral-200 bg-neutral-50/70 text-xs uppercase tracking-wider text-neutral-500 dark:border-neutral-800 dark:bg-neutral-800/40">
                                <th class="px-5 py-3.5 font-semibold">Reference</th>
                                <th class="px-5 py-3.5 font-semibold">Items</th>
                                <th class="px-5 py-3.5 font-semibold">Amount</th>
                                <th class="px-5 py-3.5 font-semibold">Status</th>
                                <th class="px-5 py-3.5 font-semibold">Date</th>
                                <th class="px-5 py-3.5 text-right font-semibold">Receipt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @foreach ($orders as $order)
                                @php
                                    $statusBadge = match ($order->status) {
                                        'PAID' => 'badge-success',
                                        'PENDING' => 'badge-gold',
                                        'FAILED' => 'badge-danger',
                                        'REFUNDED' => 'badge-neutral',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <tr class="transition hover:bg-neutral-50/50 dark:hover:bg-neutral-800/30">
                                    <td data-label="Reference" class="px-5 py-4 font-mono text-xs font-semibold text-neutral-900 dark:text-white">
                                        {{ $order->reference }}
                                    </td>
                                    <td data-label="Items" class="px-5 py-4">
                                        @forelse ($order->items as $item)
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $item->bundle->title ?? 'Resource Bundle' }}</span>
                                                @if ($item->bundle?->course)
                                                    <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                                        {{ $item->bundle->course->code }}
                                                    </span>
                                                @endif
                                            </div>
                                        @empty
                                            <span class="text-xs text-neutral-400">Past questions bundle</span>
                                        @endforelse
                                    </td>
                                    <td data-label="Amount" class="whitespace-nowrap px-5 py-4 font-semibold text-neutral-900 dark:text-white">
                                        GHS {{ number_format($order->amount_pesewas / 100, 2) }}
                                    </td>
                                    <td data-label="Status" class="whitespace-nowrap px-5 py-4">
                                        <span class="{{ $statusBadge }} font-medium text-xs">{{ $order->status }}</span>
                                    </td>
                                    <td data-label="Date" class="whitespace-nowrap px-5 py-4 text-xs text-neutral-500">
                                        {{ $order->created_at->format('d M Y, H:i') }}
                                    </td>
                                    <td data-label="" class="whitespace-nowrap px-5 py-4 text-right">
                                        <a href="{{ route('payment.result', ['ref' => $order->reference]) }}" class="btn-secondary !py-1 !px-2.5 text-xs inline-flex items-center gap-1">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                                            Receipt
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($orders->hasPages())
                    <div class="border-t border-neutral-100 p-4 dark:border-neutral-800">
                        {{ $orders->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
