@extends('admin.layout')

@section('title', 'Admin — Orders')

@section('admin-content')
    <h1 class="text-xl font-bold text-neutral-900">Orders</h1>
    <p class="mt-1 text-sm text-neutral-600">Latest orders with their payment records. Search by reference or email.</p>

    <form method="GET" class="mt-4 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Reference or email…" class="input w-56 !py-1.5 text-sm">
        <select name="status" class="input w-36 !py-1.5 text-sm">
            <option value="">All statuses</option>
            @foreach (['PENDING', 'PAID', 'FAILED', 'CANCELLED', 'PARTIALLY_REFUNDED', 'REFUNDED'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn-secondary btn-sm">Filter</button>
    </form>

    <div class="card mt-5 md:overflow-x-auto">
        <table class="table-base table-responsive">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Student</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Payment</th>
                    <th>Order status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    @php $payment = $order->payments->first(); @endphp
                    <tr>
                        <td data-label="Order" class="font-mono text-xs">{{ $order->reference }}</td>
                        <td data-label="Student">
                            <div class="md:text-right">
                                <p class="font-medium">
                                    @if ($order->user)
                                        {{ trim(($order->user->first_name ?? '').' '.($order->user->last_name ?? '')) ?: $order->user->email }}
                                    @else
                                        <span class="text-neutral-400">deleted</span>
                                    @endif
                                </p>
                                <p class="text-xs text-neutral-500">{{ $order->user?->email }}</p>
                            </div>
                        </td>
                        <td data-label="Items">
                            <div class="max-w-52 text-xs">
                                @foreach ($order->items as $i => $item)
                                    @if ($i > 0), @endif
                                    @if ($item->bundle)
                                        <a href="{{ route('bundles.show', $item->bundle->slug) }}"
                                           class="transition-colors hover:text-brand-700 hover:underline">
                                            {{ $item->bundle->course?->code ?? $item->bundle->title }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td data-label="Amount" class="whitespace-nowrap font-medium">
                            GH₵{{ number_format($order->amount_pesewas / 100, 2) }}
                            @php $refunded = $order->refunds->where('status', 'PROCESSED')->sum('amount_pesewas'); @endphp
                            @if ($refunded > 0)
                                <span class="mt-0.5 block text-[11px] font-medium text-amber-700 dark:text-amber-300">
                                    −GH₵{{ number_format($refunded / 100, 2) }} refunded
                                </span>
                            @endif
                            @if ($order->refunds->where('status', '!=', 'PROCESSED')->isNotEmpty())
                                <span class="mt-0.5 block text-[11px] text-neutral-500 dark:text-neutral-400">
                                    refund {{ strtolower($order->refunds->where('status', '!=', 'PROCESSED')->first()->status) }}
                                </span>
                            @endif
                        </td>
                        <td data-label="Method" class="text-xs">
                            {{ $payment ? $payment->provider.($payment->channel ? ' / '.$payment->channel : '') : '—' }}
                        </td>
                        <td data-label="Payment" class="text-xs">
                            @if ($payment)
                                <span class="{{ match ($payment->status) {
                                    'SUCCESSFUL' => 'badge-success',
                                    'PENDING' => 'badge-gold',
                                    'REFUNDED' => 'badge-danger',
                                    'REFUNDED_PARTIALLY' => 'badge-gold',
                                    default => 'badge-danger',
                                } }}">{{ $payment->status }}</span>
                            @else
                                <span class="text-neutral-400">no attempt</span>
                            @endif
                        </td>
                        <td data-label="Order status">
                            <span class="{{ match ($order->status) {
                                'PAID' => 'badge-success',
                                'PENDING' => 'badge-gold',
                                'FAILED' => 'badge-danger',
                                'REFUNDED' => 'badge-danger',
                                'PARTIALLY_REFUNDED' => 'badge-gold',
                                default => 'badge-neutral',
                            } }}">{{ $order->status }}</span>
                        </td>
                        <td data-label="Date" class="whitespace-nowrap text-neutral-600">{{ $order->created_at->format('d/m/Y, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-neutral-500">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
