@extends('layouts.app')

@section('title', 'Set new password — Academic Hub')

@section('content')
    <div class="container-page flex justify-center py-12">
        <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-5 shadow-soft sm:p-6 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mb-6 flex flex-col items-center">
                @include('partials.logo', ['tagline' => true])
            </div>
            
            <h1 class="text-center text-xl font-bold text-neutral-900 dark:text-neutral-100">Set new password</h1>
            <p class="mt-1 text-center text-sm text-neutral-600 dark:text-neutral-400">
                Please enter your email and choose a strong new password.
            </p>

            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
                           class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                </div>

                @include('partials.password-field', [
                    'name' => 'password',
                    'label' => 'New password',
                    'autocomplete' => 'new-password',
                    'autofocus' => true,
                    'hint' => 'At least 8 characters.',
                ])

                @include('partials.password-field', [
                    'name' => 'password_confirmation',
                    'label' => 'Confirm new password',
                    'autocomplete' => 'new-password',
                ])

                <button type="submit" class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-brand-800">
                    Reset password
                </button>
            </form>

            <p class="mt-5 text-center text-sm text-neutral-600 dark:text-neutral-400">
                <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-400">← Back to log in</a>
            </p>
        </div>
    </div>
@endsection

