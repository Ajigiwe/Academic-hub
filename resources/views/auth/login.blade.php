@extends('layouts.app')

@section('title', 'Log in — Academic Hub')

@section('content')
    {{-- Port of the Next.js AuthForm (login mode): centered brand card. --}}
    <div class="container-page flex justify-center py-12">
        <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-5 shadow-soft sm:p-6 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mb-6 flex flex-col items-center">
                @include('partials.logo', ['tagline' => true])
            </div>
            @php $finishingPayment = str_contains((string) session('url.intended'), '/payments/verify'); @endphp
            <h1 class="text-center text-xl font-bold text-neutral-900 dark:text-neutral-100">Welcome back</h1>
            <p class="mt-1 text-center text-sm text-neutral-600 dark:text-neutral-400">Log in to access your library.</p>

            @if ($finishingPayment)
                {{-- Arrived here from a payment return: reassure them that the
                     payment is not lost, and that we resume where they left off. --}}
                <p role="status" class="mt-4 rounded-lg bg-brand-50 px-3.5 py-2.5 text-sm text-brand-800 dark:bg-neutral-800 dark:text-brand-200">
                    Sign in to finish confirming your payment — we'll take you straight back to it. Nothing has been lost.
                </p>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                           class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                </div>
                @include('partials.password-field', [
                    'name' => 'password',
                    'label' => 'Password',
                    'autocomplete' => 'current-password',
                ])

                @if ($errors->any())
                    {{-- Port of the AuthForm alert: bad credentials and
                         suspended accounts both arrive via withErrors. --}}
                    <p role="alert" class="rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{{ $errors->first() }}</p>
                @elseif (session('error'))
                    <p role="alert" class="rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{{ session('error') }}</p>
                @endif

                <button type="submit" class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-brand-800">
                    Log in
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-neutral-600 dark:text-neutral-400">
                New here? <a href="{{ route('register') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Create an account</a>
            </p>
        </div>
    </div>
@endsection
