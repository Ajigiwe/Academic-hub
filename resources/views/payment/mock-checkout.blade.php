@extends('layouts.app')

@section('title', 'Checkout (sandbox)')

@section('content')
    @if ($ref === '')
        <p class="text-center text-neutral-600">Missing payment reference.</p>
    @else
        <div class="mx-auto max-w-md rounded-lg border border-neutral-200 bg-white p-6">
            <div class="text-xs font-medium uppercase tracking-wide text-amber-600">Sandbox gateway — no real money</div>
            <h1 class="mt-2 text-xl font-bold">Mock Checkout</h1>
            <p class="mt-1 text-neutral-600">Reference <span class="font-mono">{{ $ref }}</span></p>
            <p class="mt-4 text-3xl font-bold">GHS {{ number_format($amountPesewas / 100, 2) }}</p>

            <div class="mt-6 space-y-3">
                <form method="POST" action="{{ route('payment.mock-gateway') }}">
                    @csrf
                    <input type="hidden" name="ref" value="{{ $ref }}">
                    <input type="hidden" name="outcome" value="success">
                    <button class="w-full rounded-md bg-emerald-600 px-4 py-2.5 font-semibold text-white hover:bg-emerald-500">
                        Approve payment
                    </button>
                </form>
                <form method="POST" action="{{ route('payment.mock-gateway') }}">
                    @csrf
                    <input type="hidden" name="ref" value="{{ $ref }}">
                    <input type="hidden" name="outcome" value="failure">
                    <button class="w-full rounded-md border border-neutral-300 px-4 py-2.5 font-medium text-neutral-700 hover:bg-neutral-50">
                        Simulate failure
                    </button>
                </form>
                <form method="POST" action="{{ route('payment.mock-gateway') }}">
                    @csrf
                    <input type="hidden" name="ref" value="{{ $ref }}">
                    <input type="hidden" name="outcome" value="cancel">
                    <button class="w-full rounded-md px-4 py-2.5 text-sm text-neutral-500 hover:text-neutral-700">
                        Cancel
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection
