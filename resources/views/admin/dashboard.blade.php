@extends('admin.layout')

@section('title', 'Dashboard — Admin · Academic Hub')

@section('admin-content')
<?php use App\Support\Format; ?>
    <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Dashboard</h1>

    <div class="mt-5 grid grid-cols-1 gap-3 min-[400px]:grid-cols-2 sm:gap-4 lg:grid-cols-4">
        @foreach ([
            ['Total revenue', 'GHS '.number_format($revenuePesewas / 100, 2)],
            ['Total sales', number_format($paidSales)],
            ['Students', number_format($studentsCount)],
            ['Published bundles', number_format($publishedBundles)],
        ] as [$label, $value])
            <div class="card-padded min-w-0 p-4 min-[400px]:p-5">
                <p class="text-[11px] uppercase leading-snug tracking-wide text-neutral-500 sm:text-xs dark:text-neutral-400">{{ $label }}</p>
                <p class="mt-1.5 text-xl font-extrabold tabular-nums text-neutral-900 min-[400px]:text-2xl dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if ($pendingPayments > 0)
        <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ $pendingPayments }} payment{{ $pendingPayments === 1 ? '' : 's' }} awaiting
            verification — they resolve automatically via webhook or when the
            student returns from the gateway.
        </p>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-neutral-900 dark:text-white">Recent orders</h2>
                <a href="{{ route('admin.orders') }}" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300">View all</a>
            </div>
            <div class="card mt-3 overflow-x-auto">
                <table class="table-responsive w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-200 text-left text-xs uppercase tracking-wide text-neutral-500 dark:border-neutral-800">
                            <th class="px-4 py-3 font-medium">Order</th>
                            <th class="px-4 py-3 font-medium">Student</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="md:table-row-group">
                        @forelse ($recentOrders as $o)
                            <tr class="md:table-row md:border-b md:border-neutral-100 dark:md:border-neutral-800">
                                <td data-label="Order" class="px-4 py-3 font-mono text-xs">{{ $o->reference }}</td>
                                <td data-label="Student" class="px-4 py-3">
                                    <p class="font-medium text-neutral-900 dark:text-white">{{ $o->user->first_name }} {{ $o->user->last_name }}</p>
                                    <p class="text-xs text-neutral-500">{{ $o->user->email }}</p>
                                </td>
                                <td data-label="Amount" class="whitespace-nowrap px-4 py-3">GHS {{ number_format($o->amount_pesewas / 100, 2) }}</td>
                                <td data-label="Status" class="px-4 py-3">
                                    <span class="{{ $o->status === 'PAID' ? 'badge-success' : ($o->status === 'PENDING' ? 'badge-gold' : ($o->status === 'FAILED' ? 'badge-danger' : 'badge-neutral')) }}">{{ $o->status }}</span>
                                </td>
                                <td data-label="Date" class="whitespace-nowrap px-4 py-3 text-neutral-600">{{ $o->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-neutral-500" data-label="">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="min-w-0">
            <h2 class="font-bold text-neutral-900 dark:text-white">Popular bundles</h2>
            <div class="card mt-3 divide-y divide-neutral-100 dark:divide-neutral-800">
                @forelse ($popular as $i => $b)
                    <div class="flex items-center gap-3 p-3.5">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-800 dark:text-brand-300">{{ $i + 1 }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ $b->title }}</p>
                            <p class="text-xs text-neutral-500">{{ $b->course->code ?? '' }} · {{ $b->order_items_count }} {{ Str::plural('sale', $b->order_items_count) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-center text-sm text-neutral-500">No published bundles yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
