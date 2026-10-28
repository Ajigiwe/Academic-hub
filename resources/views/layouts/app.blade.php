<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2d5b">
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="#0b2d5b" />
    <meta name="msapplication-TileColor" content="#0b2d5b" />
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>@yield('title', 'Academic Hub — Find. Purchase. Study.')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
    {{-- DEV-ONLY Tailwind (compile with Vite before production) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Brand tokens — port of the Next.js @theme block (globals.css).
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef4ff', 100: '#dbe7fe', 200: '#bfd4fe', 300: '#93b4fd',
                            400: '#608afa', 500: '#3b6df6', 600: '#2563eb', 700: '#1d4ed8',
                            800: '#173aa8', 900: '#12296b', 950: '#0b2d5b',
                        },
                        gold: { 300: '#93b4fd', 400: '#608afa', 500: '#3b6df6', 600: '#1d4ed8' },
                    },
                    fontFamily: {
                        display: ['Fraunces', 'ui-serif', 'Georgia', 'Times New Roman', 'serif'],
                        sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
                    },
                    boxShadow: {
                        soft: '0 1px 2px rgb(16 24 40 / 0.04), 0 1px 3px rgb(16 24 40 / 0.06)',
                        card: '0 2px 4px -2px rgb(16 24 40 / 0.06), 0 4px 8px -2px rgb(16 24 40 / 0.08)',
                        lift: '0 4px 6px -2px rgb(16 24 40 / 0.05), 0 10px 16px -4px rgb(16 24 40 / 0.12)',
                    },
                },
            },
        };
    </script>
    {{-- Display serif for headings, like the original (vendored Fraunces) --}}
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap" as="style">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap">
    <style>
        h1, h2, h3 { font-family: Fraunces, ui-serif, Georgia, serif; letter-spacing: -0.03em; }
        /* Theme boot (port of the Next.js inline script) */
        .dark body { background-color: #0a0a0a; color: #f5f5f5; }
        .dark .surface { background-color: #171717; border-color: #262626; }
        /* Component classes — port of the Next.js @layer components
           (globals.css), hand-compiled because the CDN build has no
           @apply. */
        .btn-primary, .btn-secondary, .btn-ghost {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.625rem 1rem;
            font-size: 0.875rem; font-weight: 600; cursor: pointer;
            transition: all 150ms;
        }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }
        .btn-primary { background: #1d4ed8; color: #fff; box-shadow: 0 1px 2px rgb(16 24 40 / 0.06); }
        .btn-primary:hover { background: #173aa8; }
        .btn-secondary { border: 1px solid #d4d4d4; background: #fff; color: #262626; box-shadow: 0 1px 2px rgb(16 24 40 / 0.06); }
        .btn-secondary:hover { background: #f5f5f5; }
        .dark .btn-secondary { border-color: #404040; background: #171717; color: #e5e5e5; }
        .dark .btn-secondary:hover { background: #262626; }
        .btn-ghost { color: #404040; padding: 0.375rem 0.5rem; }
        .btn-ghost:hover { background: #f5f5f5; }
        .dark .btn-ghost { color: #d4d4d4; }
        .dark .btn-ghost:hover { background: #262626; }
        /* Cards + badges — same hand-compiled port of the Next.js
           globals.css component layer. */
        .card {
            border-radius: 0.75rem; border: 1px solid #e5e5e5; background: #fff;
            box-shadow: 0 1px 2px rgb(16 24 40 / 0.04), 0 1px 3px rgb(16 24 40 / 0.06);
        }
        .card-hover { transition: box-shadow 0.2s ease, transform 0.2s ease; }
        .card-hover:hover { box-shadow: 0 4px 12px rgb(16 24 40 / 0.10), 0 2px 4px rgb(16 24 40 / 0.06); }
        .dark .card { border-color: #262626; background: #171717; }
        .card-padded {
            border-radius: 0.75rem; border: 1px solid #e5e5e5; background: #fff;
            padding: 1.25rem; box-shadow: 0 1px 2px rgb(16 24 40 / 0.04), 0 1px 3px rgb(16 24 40 / 0.06);
        }
        .dark .card-padded { border-color: #262626; background: #171717; }
        .badge, .badge-brand, .badge-neutral, .badge-gold, .badge-success, .badge-danger {
            display: inline-flex; align-items: center; border-radius: 9999px;
            padding: 0.125rem 0.625rem; font-size: 0.75rem; font-weight: 500; line-height: 1.25rem;
        }
        .badge-brand { background: #eff6ff; color: #1d4ed8; }
        .badge-neutral { background: #f5f5f5; color: #404040; }
        .badge-gold { background: #fffbeb; color: #b45309; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .dark .badge-brand { background: rgb(37 99 235 / 0.15); color: #93b4fd; }
        .dark .badge-neutral { background: #262626; color: #d4d4d4; }
        .dark .badge-gold { background: rgb(245 158 11 / 0.12); color: #fcd34d; }
        .dark .badge-success { background: rgb(34 197 94 / 0.12); color: #86efac; }
        .dark .badge-danger { background: rgb(239 68 68 / 0.12); color: #fca5a5; }
        /* Dark-mode shim — the views were authored light-only (the original
           uses dark: variants everywhere); map the common neutral/brand
           utilities so dark is readable until the Vite build ports them
           properly. */
        .dark .bg-white { background-color: #171717; }
        .dark .bg-neutral-50 { background-color: #0f0f0f; }
        .dark .bg-neutral-100 { background-color: #262626; }
        .dark .border-neutral-200 { border-color: #262626; }
        .dark .border-neutral-300 { border-color: #404040; }
        .dark .text-neutral-900 { color: #f5f5f5; }
        .dark .text-neutral-800 { color: #e5e5e5; }
        .dark .text-neutral-700 { color: #d4d4d4; }
        .dark .text-neutral-600 { color: #a3a3a3; }
        .dark .hover\\:bg-neutral-50:hover, .dark .hover\\:bg-neutral-100:hover { background-color: #262626; }
        .dark .hover\\:bg-brand-50:hover { background-color: rgb(37 99 235 / 0.15); }
        .dark .hover\\:border-brand-300:hover { border-color: #1d4ed8; }
        .dark .bg-brand-50 { background-color: rgb(37 99 235 / 0.15); }
        .dark .bg-brand-100 { background-color: rgb(37 99 235 / 0.25); }
        .dark .text-brand-600, .dark .text-brand-700, .dark .text-brand-800 { color: #93b4fd; }
        .dark .bg-green-50, .dark .bg-emerald-50 { background-color: rgb(34 197 94 / 0.12); }
        .dark .text-green-800, .dark .text-green-700, .dark .text-emerald-800, .dark .text-emerald-700 { color: #86efac; }
        .dark .bg-red-50 { background-color: rgb(239 68 68 / 0.12); }
        .dark .text-red-700, .dark .text-red-800 { color: #fca5a5; }
        .dark .bg-amber-50 { background-color: rgb(245 158 11 / 0.12); }
        .dark .text-amber-600, .dark .text-amber-700, .dark .text-amber-800 { color: #fcd34d; }
        .dark input, .dark select, .dark textarea { color-scheme: dark; }
        .dark ::placeholder { color: #737373; opacity: 1; }
        /* PWA install banner — hidden until JS sets data-state="show".
           (The Tailwind classes on the element can't do this: the
           group-data variant needs a parent group + data attr, and the
           element carries opacity-0 which never gets overridden.) */
        #install-banner { transform: translateY(-100%); opacity: 0; visibility: hidden;
            transition: transform .3s ease, opacity .3s ease; }
        #install-banner[data-state="show"] { transform: translateY(0); opacity: 1; visibility: visible; }
    </style>
    <script>
        try {
            var t = localStorage.getItem('arh-theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
</head>
<body class="flex min-h-screen flex-col bg-neutral-50 font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100 @yield('body-class')">
{{-- A view can opt out of the site chrome by declaring @section('immersive')
     — the reader does, so the paper gets the whole screen instead of a
     panel between a header and a nav bar. --}}
@unless (View::hasSection('immersive'))
<header id="site-header" class="sticky top-0 z-40 border-b border-neutral-200/80 bg-white/90 backdrop-blur-md dark:border-neutral-800/80 dark:bg-neutral-950/90">
    {{-- Brand accent line --}}
    <div class="h-0.5 w-full bg-gradient-to-r from-brand-700 via-gold-400 to-brand-950"></div>
    <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="group">
            @include('partials.logo', ['tagline' => true])
        </a>

        {{-- Desktop nav --}}
        <nav class="hidden items-center gap-1 text-sm font-medium text-neutral-700 dark:text-neutral-300 md:flex">
            <a href="{{ route('courses') }}" class="rounded-lg px-3 py-2 transition-colors hover:bg-brand-50 hover:text-brand-800 dark:hover:bg-brand-950 dark:hover:text-brand-300">Browse</a>
            <a href="{{ route('search') }}" class="rounded-lg px-3 py-2 transition-colors hover:bg-brand-50 hover:text-brand-800 dark:hover:bg-brand-950 dark:hover:text-brand-300">Search</a>
            @auth
                <a href="{{ route('library') }}" class="rounded-lg px-3 py-2 transition-colors hover:bg-brand-50 hover:text-brand-800 dark:hover:bg-brand-950 dark:hover:text-brand-300">Library</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-2 transition-colors hover:bg-brand-50 hover:text-brand-800 dark:hover:bg-brand-950 dark:hover:text-brand-300">Admin</a>
                @endif
            @endauth
        </nav>

        <div class="hidden items-center gap-2.5 md:flex">
            <button type="button" onclick="cycleTheme(this)" class="btn-ghost btn-sm" aria-label="Toggle theme" title="Theme">
                <svg class="theme-icon-sun hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                <svg class="theme-icon-moon hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </button>
            @auth
                <?php $unread = \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count(); ?>
                <a href="{{ route('notifications.index') }}" aria-label="Notifications" class="relative grid h-9 w-9 place-items-center rounded-full text-neutral-600 transition-colors hover:bg-brand-50 hover:text-brand-800 dark:text-neutral-300 dark:hover:bg-brand-950" title="Notifications">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    @if ($unread > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>
                <a href="#" class="flex items-center gap-2 rounded-full border border-neutral-200 bg-white py-1 pl-1 pr-3 shadow-sm transition hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-brand-600">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-300">{{ mb_strtoupper(mb_substr(auth()->user()->first_name ?? auth()->user()->name ?? 'S', 0, 1)) }}</span>
                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ auth()->user()->first_name ?? auth()->user()->name ?? 'Account' }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary btn-sm">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-secondary btn-sm">Log in</a>
                <a href="{{ route('register') }}" class="btn-primary btn-sm">Sign up</a>
            @endauth
        </div>

        {{-- Compact mobile actions (the original hides sign-up below md) --}}
        <div class="flex items-center gap-2 md:hidden">
            <button type="button" onclick="cycleTheme(this)" class="btn-ghost btn-sm" aria-label="Toggle theme">
                <svg class="theme-icon-sun hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                <svg class="theme-icon-moon hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </button>
            @auth
                <?php $unread = \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count(); ?>
                <a href="{{ route('notifications.index') }}" aria-label="Notifications" class="relative grid h-8 w-8 place-items-center rounded-full text-neutral-600 dark:text-neutral-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    @if ($unread > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </a>
            @else
                <a href="{{ route('login') }}" class="btn-primary btn-sm">Log in</a>
            @endauth
        </div>
    </div>
</header>
@endunless

<main class="mx-auto w-full max-w-6xl flex-1 px-4 pb-28 sm:px-6 md:pb-8 {{ request()->routeIs('home') ? '' : 'pt-8' }}">
    @if (session('status'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>

{{-- Mobile bottom nav (port of mobile-nav.tsx): Home · Browse · Library · Log in --}}
@unless (View::hasSection('immersive'))
<nav id="mobile-nav" class="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md dark:border-neutral-800 dark:bg-neutral-950/95 md:hidden">
    <div class="grid grid-flow-col auto-cols-fr">
        @php
            $mobileItems = [
                ['href' => route('home'), 'label' => 'Home', 'match' => request()->path() === '/', 'icon' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
                ['href' => route('search'), 'label' => 'Search', 'match' => request()->is('search*') || request()->is('courses*'), 'icon' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>'],
            ];
            if (auth()->check()) {
                $mobileItems[] = ['href' => route('library'), 'label' => 'Library', 'match' => request()->is('library*'), 'icon' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>'];
                if (auth()->user()->isAdmin()) {
                    $mobileItems[] = ['href' => route('admin.dashboard'), 'label' => 'Admin', 'match' => request()->is('admin*'), 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>'];
                }
                $mobileItems[] = ['href' => route('favourites.index'), 'label' => 'Saved', 'match' => request()->is('favourites*'), 'icon' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'];
            } else {
                $mobileItems[] = ['href' => route('login'), 'label' => 'Log in', 'match' => request()->is('login*'), 'icon' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>'];
            }
        @endphp
        @foreach ($mobileItems as $item)
            <a href="{{ $item['href'] }}" class="flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium transition-colors {{ $item['match'] ? 'text-brand-700 dark:text-brand-400' : 'text-neutral-500 dark:text-neutral-400' }}">
                <span class="rounded-lg px-3 py-0.5 transition-colors {{ $item['match'] ? 'bg-brand-50 dark:bg-brand-950' : '' }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                </span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
@endunless

@stack('scripts')
<script>
    // PWA: register the service worker (the browser only offers the
    // install prompt for a registered worker + manifest).
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function (err) {
                console.warn('[SW] Registration failed:', err);
            });
        });
    }

    // PWA: capture the browser's install prompt in the page (it fires
    // on window, NOT in the service worker), stash it for the Install
    // button, and notify the banner below to show itself.
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); // suppress Chrome's mini-infobar
        window.deferredPrompt = e;
        window.dispatchEvent(new CustomEvent('sw:installprompt', { detail: e }));
    });
</script>
<script>
    // Theme toggle — cycles light/dark, persists in the same key as the
    // original app ('arh-theme').
    function cycleTheme(btn) {
        var dark = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('arh-theme', dark ? 'dark' : 'light'); } catch (e) {}
        if (btn) updateThemeIcons();
    }
    function updateThemeIcons() {
        var dark = document.documentElement.classList.contains('dark');
        document.querySelectorAll('.theme-icon-sun').forEach(function (el) { el.classList.toggle('hidden', dark); });
        document.querySelectorAll('.theme-icon-moon').forEach(function (el) { el.classList.toggle('hidden', !dark); });
    }
    updateThemeIcons();

    // Password visibility — any [data-password-toggle] button flips the
    // input it names between masked and readable (see
    // partials/password-field). Delegated, so it also covers fields that
    // are added to the page later. Without JS the fields stay masked.
    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('[data-password-toggle]') : null;
        if (!btn) return;

        var input = document.getElementById(btn.getAttribute('data-password-toggle'));
        if (!input) return;

        var revealed = input.type === 'password';
        input.type = revealed ? 'text' : 'password';

        btn.setAttribute('aria-pressed', revealed ? 'true' : 'false');
        var label = revealed ? 'Hide password' : 'Show password';
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);

        // Masked: an open eye ("show it"). Revealed: a struck-out eye
        // ("hide it again"), so the icon always describes the next click.
        btn.querySelectorAll('.pw-icon-show').forEach(function (el) { el.classList.toggle('hidden', revealed); });
        btn.querySelectorAll('.pw-icon-hide').forEach(function (el) { el.classList.toggle('hidden', !revealed); });
    });
</script>
<!-- Install banner shown to users who havent added the app to their home screen -->
<div id="install-banner" class="fixed top-0 left-0 right-0 z-50 bg-neutral-900/95 text-neutral-100 padding px-4 py-3 text-sm shadow-lg transition-transform transform translate-y-full opacity-0 group-data-[state=show]/translate-y-0 opacity-1 transition-transform duration-300" aria-live="polite">
    <div class="max-w-2xl mx-auto text-center">
        <div class="flex items-center justify-center gap-3">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7a2 2 0 0 0-2 2H5a2 2 0 0 0-2 2 2 2 0 0 0 0 4 2 2 0 0 0 2 2h14a2 2 0 0 0 2-2 2 2 0 0 0 0-4z"/><polyline points="17 3 21 7 21 7"/><line points="7 21" points="3 17"/><line points="7 3" points="15 3"/></svg>
            <span>Install Academic Hub</span>
        </div>
        <p class="mt-1 text-neutral-400">Get offline access to your papers and resources</p>
        <p id="install-hint" class="hidden mt-1 text-neutral-300">Open your browser menu (⋯ or the Share icon) and choose <strong>Install app</strong> or <strong>Add to Home Screen</strong>.</p>
        <div class="mt-3 flex gap-2">
            <button id="install-btn" class="btn-primary">Install</button>
            <button id="cancel-install" class="btn-secondary">Later</button>
        </div>
    </div>
</div>

<script>
    // PWA Install Banner
    let showInstallBanner = true;

    // Listen for the install prompt event from the service worker
    window.addEventListener("sw:installprompt", (e) => {
        const event = e.detail;
        // Show the install banner
        const banner = document.getElementById("install-banner");
        if (banner) {
            banner.dataset.state = "show";
            // Remove any existing listener to avoid duplicates
            const newBanner = banner.cloneNode(true);
            banner.parentNode.replaceChild(newBanner, banner);
        }
        // Allow showing the banner once per session
        showInstallBanner = false;
    });

    // Handle install button click
    document.addEventListener("click", function(e) {
        const btn = e.target.closest ? e.target.closest("#install-btn") : null;
        if (!btn) return;

        // Hide banner
        const banner = document.getElementById("install-banner");
        if (banner) {
            banner.dataset.state = "hide";
        }

        // Trigger the PWA install
        if (window.deferredPrompt) {
            window.deferredPrompt.prompt();
            window.deferredPrompt.userChoice.then((choiceResult) => {
                window.deferredPrompt = null;
                // Accepted or dismissed — either way, stop nagging.
                try { localStorage.setItem('arh-install-dismissed', '1'); } catch (err) {}
            });
        } else {
            // No native prompt (Firefox/Safari, or Chrome hasn't fired it
            // yet) — show manual instructions instead of doing nothing.
            const hint = document.getElementById('install-hint');
            if (hint) hint.classList.remove('hidden');
        }
    });

    // Handle cancel button
    document.addEventListener("click", function(e) {
        const btn = e.target.closest ? e.target.closest("#cancel-install") : null;
        if (!btn) return;
        const banner = document.getElementById("install-banner");
        if (banner) banner.dataset.state = "hide";
        // Remember the dismissal — don't nag this browser again.
        try { localStorage.setItem('arh-install-dismissed', '1'); } catch (err) {}
    });

    // Fallback: Chrome only fires beforeinstallprompt when its install
    // heuristics pass (user engagement, not previously dismissed prompts,
    // not already installed…). If it hasn't fired within 3 seconds of
    // load, show our banner anyway — clicking Install without a native
    // prompt reveals manual instructions. A real prompt that fires later
    // simply reuses this same banner.
    (function () {
        let dismissed = false;
        try { dismissed = localStorage.getItem('arh-install-dismissed') === '1'; } catch (err) {}
        if (dismissed) return;
        const banner = document.getElementById('install-banner');
        if (!banner) return;
        setTimeout(function () {
            if (banner.dataset.state !== 'show') banner.dataset.state = 'show';
        }, 3000);
    })();

</script>
</body>
</html>
