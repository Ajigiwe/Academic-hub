<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2d5b">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin — Academic Hub')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icon.svg') }}">
    {{-- DEV-ONLY Tailwind (compile with Vite before production) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
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
                    },
                },
            },
        };
    </script>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap" as="style">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&display=swap">
    <style>
        h1, h2, h3 { font-family: Fraunces, ui-serif, Georgia, serif; letter-spacing: -0.03em; }
        .dark body { background-color: #0a0a0a; color: #f5f5f5; }
        /* Component classes — same hand-compiled port as layouts/app. */
        .btn-primary, .btn-secondary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.625rem 1rem;
            font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 150ms;
        }
        .btn-primary { background: #1d4ed8; color: #fff; box-shadow: 0 1px 2px rgb(16 24 40 / 0.06); }
        .btn-primary:hover { background: #173aa8; }
        .btn-secondary { border: 1px solid #d4d4d4; background: #fff; color: #262626; box-shadow: 0 1px 2px rgb(16 24 40 / 0.06); }
        .btn-secondary:hover { background: #f5f5f5; }
        .btn-danger { background: #dc2626; color: #fff; box-shadow: 0 1px 2px rgb(16 24 40 / 0.06); }
        .btn-danger:hover { background: #b91c1c; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; line-height: 1rem; }
        /* Form primitives — port of .label/.input (globals.css). */
        .label { display: block; margin-bottom: 0.375rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #525252; }
        .input {
            width: 100%; border-radius: 0.5rem; border: 1px solid #d4d4d4; background: #fff;
            padding: 0.625rem 0.875rem; font-size: 1rem; color: #171717; box-shadow: 0 1px 2px rgb(16 24 40 / 0.04);
            outline: none; transition: border-color 150ms, box-shadow 150ms;
        }
        .input::placeholder { color: #a3a3a3; }
        .input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgb(37 99 235 / 0.2); }
        /* Desktop table base — port of .table-base (globals.css). */
        .table-base { width: 100%; text-align: left; font-size: 0.875rem; }
        .table-base thead { border-bottom: 1px solid #e5e5e5; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #737373; }
        .table-base th { padding: 0.75rem 1rem; font-weight: 600; }
        .table-base td { border-bottom: 1px solid #f5f5f5; padding: 0.75rem 1rem; vertical-align: middle; }
        /* Cells inside label-less rows (actions) align left like the original. */
        .table-base td[data-label=""] { text-align: left; }
        .card, .card-padded {
            border-radius: 0.75rem; border: 1px solid #e5e5e5; background: #fff;
            box-shadow: 0 1px 2px rgb(16 24 40 / 0.04), 0 1px 3px rgb(16 24 40 / 0.06);
        }
        .card-padded { padding: 1.25rem; }
        .badge, .badge-brand, .badge-neutral, .badge-gold, .badge-success, .badge-danger {
            display: inline-flex; align-items: center; border-radius: 9999px;
            padding: 0.125rem 0.625rem; font-size: 0.75rem; font-weight: 500; line-height: 1.25rem;
        }
        .badge-brand { background: #eff6ff; color: #1d4ed8; }
        .badge-neutral { background: #f5f5f5; color: #404040; }
        .badge-gold { background: #fffbeb; color: #b45309; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        /* Component dark overrides — mirrored from layouts/app (the admin
           console reads on a dark chrome, so cards must follow the theme). */
        .dark .card, .dark .card-padded { border-color: #262626; background: #171717; }
        .dark .badge-brand { background: rgb(37 99 235 / 0.15); color: #93b4fd; }
        .dark .badge-neutral { background: #262626; color: #d4d4d4; }
        .dark .badge-gold { background: rgb(245 158 11 / 0.12); color: #fcd34d; }
        .dark .badge-success { background: rgb(34 197 94 / 0.12); color: #86efac; }
        .dark .badge-danger { background: rgb(239 68 68 / 0.12); color: #fca5a5; }
        .dark .table-base thead { border-bottom-color: #262626; color: #a3a3a3; }
        .dark .table-base td { border-bottom-color: #1f1f1f; }
        .dark .divide-neutral-100 > * + *, .dark .divide-neutral-200 > * + * { border-color: #262626; }
        /* Admin sidebar links — port of .admin-nav-link (globals.css). */
        .admin-nav-link {
            display: flex; align-items: center; gap: 0.625rem; border-radius: 0.5rem;
            padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 500; color: #d4d4d4;
            transition: all 150ms;
        }
        .admin-nav-link:hover { background: rgb(255 255 255 / 0.05); color: #fff; }
        .admin-nav-link-active, .admin-nav-link-active:hover {
            display: flex; align-items: center; gap: 0.625rem; border-radius: 0.5rem;
            padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 500;
            background: rgb(255 255 255 / 0.1); color: #fff;
        }
        /* Responsive table — cards per row on phones (port of table-responsive). */
        @media (max-width: 767px) {
            .table-responsive { display: block; }
            .table-responsive thead { display: none; }
            .table-responsive tbody { display: flex; flex-direction: column; gap: 0.75rem; }
            .table-responsive tr {
                display: block; border: 1px solid #e5e5e5; border-radius: 0.75rem;
                background: #fff; box-shadow: 0 1px 2px rgb(16 24 40 / 0.05); overflow: hidden;
            }
            .table-responsive td {
                display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem;
                border-bottom: 1px solid #f5f5f5; padding: 0.625rem 1rem; text-align: right;
            }
            .table-responsive td:last-child { border-bottom: none; }
            .table-responsive td::before {
                content: attr(data-label);
                font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
                color: #737373; text-align: left; flex-shrink: 0;
            }
            /* Label-less cells become full-width footer rows */
            .table-responsive td[data-label=""] {
                justify-content: flex-start; text-align: left; background: #fafafa;
            }
            .table-responsive td[data-label=""]::before { content: none; }
            /* First cell acts as the card header — the label stacks above
               the value so long titles get the full card width. */
            .table-responsive td[data-label]:first-child {
                display: block; text-align: left;
                background: linear-gradient(to bottom, #fafafa, transparent); font-weight: 600;
            }
            .table-responsive td:first-child::before {
                display: block; margin-bottom: 0.25rem; color: #173aa8;
            }
            /* Full-bleed empty-state cells */
            .table-responsive td[colspan] { display: block; text-align: center; }
            .table-responsive td[colspan]::before { content: none; }
            /* Value side may wrap; the label never shrinks */
            .table-responsive td > * { min-width: 0; }
            .table-responsive td > span, .table-responsive td > a,
            .table-responsive td > p, .table-responsive td > div { overflow-wrap: anywhere; }
            /* Inline action groups inside footer rows wrap */
            .table-responsive td[data-label=""] form { display: inline-flex; margin: 0.125rem 0; }
        }
        /* Dark shim */
        .dark .bg-white { background-color: #171717; }
        .dark .bg-neutral-50 { background-color: #0f0f0f; }
        .dark .bg-neutral-100 { background-color: #262626; }
        .dark .border-neutral-200 { border-color: #262626; }
        .dark .border-neutral-100 { border-color: #262626; }
        .dark .border-neutral-300 { border-color: #404040; }
        .dark .text-neutral-900 { color: #f5f5f5; }
        .dark .text-neutral-800 { color: #e5e5e5; }
        .dark .text-neutral-700 { color: #d4d4d4; }
        .dark .text-neutral-600 { color: #a3a3a3; }
        .dark .text-neutral-500 { color: #a3a3a3; }
        .dark .bg-brand-50 { background-color: rgb(37 99 235 / 0.15); }
        .dark .text-brand-600, .dark .text-brand-700, .dark .text-brand-800 { color: #93b4fd; }
        .dark .bg-amber-50 { background-color: rgb(245 158 11 / 0.12); }
        .dark .text-amber-800 { color: #fcd34d; }
        .dark .bg-green-50, .dark .bg-emerald-50 { background-color: rgb(34 197 94 / 0.12); }
        .dark .text-green-800, .dark .text-green-700,
        .dark .text-emerald-800, .dark .text-emerald-700 { color: #86efac; }
        .dark .bg-red-50 { background-color: rgb(239 68 68 / 0.12); }
        .dark .text-red-700, .dark .text-red-800 { color: #fca5a5; }
        .dark input, .dark select, .dark textarea { color-scheme: dark; }
        .dark ::placeholder { color: #737373; opacity: 1; }
        @media (max-width: 767px) {
            .dark .table-responsive tr { background: #171717; border-color: #262626; }
            .dark .table-responsive td[data-label=""] { background: #0f0f0f; }
            .dark .table-responsive td[data-label]:first-child { background: linear-gradient(to bottom, #171717, transparent); }
        }
        .dark .input { background: #171717; border-color: #404040; color: #f5f5f5; }
        .dark .label { color: #a3a3a3; }
        .dark .btn-secondary { background: #262626; border-color: #404040; color: #e5e5e5; }
        .dark .btn-secondary:hover { background: #333; }
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
<body class="min-h-screen bg-neutral-100 font-sans antialiased dark:bg-neutral-950">
@php
    $adminNav = [
        ['admin.dashboard', 'Dashboard', '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>'],
        ['admin.bundles', 'Bundles', '<path d="M16 8 4 14"/><path d="M20 12 8 18"/><path d="M8 2 16 8v8l-8 6V8Z"/><path d="M4 14v8l8-6"/>'],
        ['admin.papers', 'Papers', '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/>'],
        ['admin.materials', 'Free materials', '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>'],
        ['admin.orders', 'Orders', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>'],
        ['admin.students', 'Students', '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'],
        ['admin.settings', 'Settings', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
    ];
    $activeRoute = request()->route()->getName();
    $isActive = function ($route) use ($activeRoute) {
        return $route === 'admin.dashboard'
            ? $activeRoute === 'admin.dashboard'
            : $activeRoute !== null && (str_starts_with($activeRoute, str_replace('.dashboard', '', $route).'.' ) || $activeRoute === $route);
    };
@endphp

{{-- Desktop console rail (pinned to the viewport edge) --}}
<aside class="fixed bottom-0 left-0 top-0 z-30 hidden w-60 md:block">
    <div class="flex h-full flex-col overflow-y-auto bg-neutral-950">
        <div class="border-b border-white/10 px-5 py-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-400">Administration</p>
            <p class="mt-1 text-sm font-semibold text-white">Control Center</p>
        </div>
        <nav class="flex-1 space-y-1 px-3 py-4">
            @foreach ($adminNav as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="{{ $isActive($route) ? 'admin-nav-link-active' : 'admin-nav-link' }}">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 px-3 py-3">
            <a href="{{ route('home') }}" class="admin-nav-link" style="font-size: 0.75rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                Back to site
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="admin-nav-link w-full" style="font-size: 0.75rem; font-weight: 600;">Log out</button>
            </form>
        </div>
    </div>
</aside>

{{-- Content column --}}
<div class="md:pl-60">
    {{-- Mobile console app bar --}}
    <div class="sticky top-0 z-40 bg-neutral-950 px-4 py-3 md:hidden">
        <div class="flex items-center gap-3">
            <button type="button" onclick="adminDrawer(true)" aria-label="Open admin menu"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-white transition-colors hover:bg-white/10">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
            </button>
            <a href="{{ route('home') }}" class="flex flex-1 flex-col">
                <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-amber-400">Administration</span>
                <span class="text-sm font-semibold text-white">Control Center</span>
            </a>
        </div>
    </div>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">
        {{-- Single source of truth for flash messages — page views must not
             render session('status')/('error') themselves. --}}
        @if (session('status'))
            <p class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:!border-green-900 dark:!bg-green-950 dark:!text-green-300">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:!border-red-900 dark:!bg-red-950 dark:!text-red-300">{{ session('error') }}</p>
        @endif

        @yield('admin-content')
    </main>
</div>

{{-- Mobile slide-in drawer --}}
<div id="admin-drawer" class="fixed inset-0 z-50 hidden md:hidden" aria-hidden="true">
    <div id="admin-drawer-backdrop" class="absolute inset-0 bg-black/60 opacity-0 transition-opacity duration-200" onclick="adminDrawer(false)"></div>
    <aside id="admin-drawer-panel" class="absolute left-0 top-0 flex h-full w-64 -translate-x-full flex-col bg-neutral-950 shadow-2xl transition-transform duration-200">
        <div class="flex items-start justify-between border-b border-white/10 px-5 py-5">
            <a href="{{ route('home') }}">
                <span class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-400">Administration</span>
                <span class="block text-sm font-semibold text-white">Control Center</span>
            </a>
            <button type="button" onclick="adminDrawer(false)" aria-label="Close admin menu"
                    class="grid h-8 w-8 place-items-center rounded-lg text-neutral-400 transition-colors hover:bg-white/10 hover:text-white">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($adminNav as [$route, $label, $icon])
                <a href="{{ route($route) }}" onclick="adminDrawer(false)" class="{{ $isActive($route) ? 'admin-nav-link-active' : 'admin-nav-link' }}">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-white/10 px-3 py-4">
            <div class="flex items-center gap-2.5 px-2">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-white/10 text-xs font-bold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->first_name ?? 'A', 0, 1)) }}</span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-white">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</p>
                    <p class="truncate text-[11px] text-neutral-500">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2 px-1">
                @csrf
                <button type="submit" class="admin-nav-link w-full">Log out</button>
            </form>
        </div>
    </aside>
</div>

<script>
    function adminDrawer(open) {
        var wrap = document.getElementById('admin-drawer');
        var backdrop = document.getElementById('admin-drawer-backdrop');
        var panel = document.getElementById('admin-drawer-panel');
        wrap.classList.toggle('hidden', !open);
        wrap.setAttribute('aria-hidden', String(!open));
        requestAnimationFrame(function () {
            backdrop.classList.toggle('opacity-100', open);
            backdrop.classList.toggle('opacity-0', !open);
            panel.classList.toggle('translate-x-0', open);
            panel.classList.toggle('-translate-x-full', !open);
        });
        document.body.style.overflow = open ? 'hidden' : '';
        if (open) {
            document.addEventListener('keydown', adminDrawerEsc);
        } else {
            document.removeEventListener('keydown', adminDrawerEsc);
        }
    }
    function adminDrawerEsc(e) { if (e.key === 'Escape') adminDrawer(false); }
</script>
@stack('scripts')
</body>
</html>
