@extends('layouts.app')

@section('title', 'Register — Academic Hub')

@section('content')
    {{-- Port of the Next.js AuthForm (register mode): same brand card. --}}
    <div class="container-page flex justify-center py-12">
        <div class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-5 shadow-soft sm:p-6 dark:border-neutral-800 dark:bg-neutral-900">
            <div class="mb-6 flex flex-col items-center">
                @include('partials.logo', ['tagline' => true])
            </div>
            <h1 class="text-center text-xl font-bold text-neutral-900 dark:text-neutral-100">Create your account</h1>
            <p class="mt-1 text-center text-sm text-neutral-600 dark:text-neutral-400">Find, purchase, and study past questions.</p>

            <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="first_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">First name</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name"
                               class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                        @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="last_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Last name</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name"
                               class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                        @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Phone (optional)</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="024 000 0000"
                           class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                           class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @include('partials.password-field', [
                    'name' => 'password',
                    'label' => 'Password',
                    'autocomplete' => 'new-password',
                    'hint' => 'At least 8 characters.',
                ])

                @include('partials.password-field', [
                    'name' => 'password_confirmation',
                    'label' => 'Confirm password',
                    'autocomplete' => 'new-password',
                ])

                @if (session('error'))
                    <p role="alert" class="rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{{ session('error') }}</p>
                @endif

                <button type="submit" class="w-full rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-brand-800">
                    Create account
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-neutral-600 dark:text-neutral-400">
                Already have an account? <a href="{{ route('login') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Log in</a>
            </p>
        </div>
    </div>
@endsection
