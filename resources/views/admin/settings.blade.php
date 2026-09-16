@extends('admin.layout')

@section('title', 'Settings — Admin')

@section('admin-content')
    <h1 class="text-xl font-bold text-neutral-900">Settings</h1>
    <p class="mt-1 text-sm text-neutral-600">
        Platform-wide controls. Changes apply to the public site immediately.
    </p>

    @php
        // How the live gateway is wired right now (PaymentManager::status).
        $paymentsLive = $payments['effective'] === 'paystack' && $payments['effective_mode'] === 'live';
        $paymentsTest = $payments['effective'] === 'paystack' && $payments['effective_mode'] === 'test';
        $paymentsBadge = $paymentsLive ? 'badge-success' : ($paymentsTest ? 'badge-gold' : 'badge-neutral');
        $paymentsLabel = $paymentsLive ? 'Paystack · live' : ($paymentsTest ? 'Paystack · test keys' : 'Sandbox · no real money');
    @endphp

    {{-- ── Payments gateway ────────────────────────────────────────── --}}
    <section class="card mt-5 max-w-2xl p-5">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Payments</h2>
            <span class="{{ $paymentsBadge }} font-semibold">{{ $paymentsLabel }}</span>
            @if ($payments['falling_back'])
                <span class="badge-neutral">falling back to sandbox</span>
            @endif
        </div>

        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
            Checkout runs through {{ $payments['effective'] === 'paystack' ? 'Paystack (card and mobile money)' : 'the sandbox gateway' }},
            and every payment is verified server-to-server before a paper unlocks. Keys live in the
            environment — never in this page or the database.
        </p>

        @if ($payments['note'])
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                {{ $payments['note'] }}
            </p>
        @endif

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Selected provider</dt>
                <dd class="mt-0.5 text-sm text-neutral-900 dark:text-neutral-100">
                    {{ ucfirst($payments['selected']) }}
                    <span class="text-neutral-500 dark:text-neutral-400">
                        {{ $payments['selected_ready'] ? '(ready)' : '(not configured)' }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Sandbox gateway</dt>
                <dd class="mt-0.5 text-sm text-neutral-900 dark:text-neutral-100">
                    {{ $payments['sandbox_available'] ? 'Available at /payment/mock-checkout' : 'Disabled' }}
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Webhook URL (paste into the Paystack dashboard)</dt>
                <dd class="mt-0.5 break-all font-mono text-xs text-neutral-900 dark:text-neutral-100">{{ $payments['webhook_url'] }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Callback URL (students return here)</dt>
                <dd class="mt-0.5 break-all font-mono text-xs text-neutral-900 dark:text-neutral-100">{{ $payments['callback_url'] }}</dd>
            </div>
        </dl>

        @if ($paymentsLive)
            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-950 dark:text-red-300">
                Live keys are active: every checkout charges real money. Set
                <span class="font-mono">PAYMENT_PROVIDER=mock</span> to run the sandbox instead.
            </p>
        @elseif (! $payments['selected_ready'] || $payments['selected'] !== 'paystack')
            <p class="mt-3 text-xs text-neutral-500 dark:text-neutral-400">
                To switch to Paystack set <span class="font-mono">PAYMENT_PROVIDER=paystack</span> and
                <span class="font-mono">PAYSTACK_SECRET_KEY=sk_test_…</span> (or <span class="font-mono">sk_live_…</span>)
                in the environment, then clear the config cache.
            </p>
        @endif

        @if (! $payments['webhook_reachable'])
            <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                APP_URL points at localhost, so Paystack cannot deliver webhooks to this machine — expose it
                through a tunnel or a deployment and use that public URL above.
            </p>
        @endif
    </section>

    {{-- ── Platform bundle price ───────────────────────────────────── --}}
    <section class="card mt-5 max-w-2xl p-5">
        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Bundle price</h2>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
            Every bundle sells for this one amount — students pay once per course and academic year and
            unlock every paper inside it. Changing it here re-prices all
            {{ number_format($bundleCount) }} bundle(s) at once; students who already bought keep the papers
            they paid for, and past orders keep the price they were sold at.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Current price</p>
                <p class="mt-0.5 text-2xl font-extrabold tracking-tight text-brand-800 dark:text-brand-300">GHS {{ $price }}</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.pricing') }}" class="flex items-end gap-2">
                @csrf
                <label>
                    <span class="label">New price (GH₵)</span>
                    <input name="price" type="number" min="0" max="1000" step="0.50" required
                           value="{{ $price }}" class="input w-32 py-2 text-right text-sm tabular-nums">
                </label>
                <button type="submit" class="btn-primary">Save price</button>
            </form>
        </div>
        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
            Stored as integer pesewas, so the amount never drifts with floating-point rounding.
        </p>
        @if ($outOfSyncCount > 0)
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                {{ $outOfSyncCount }} bundle(s) still carry a different price in the database — saving above
                re-syncs them to the platform price.
            </p>
        @endif
    </section>

    {{-- ── Programme visibility ───────────────────────────────────── --}}
    <section class="card mt-5 max-w-2xl p-5">
        <h2 class="text-base font-semibold text-neutral-900">Programmes</h2>
        <p class="mt-1 text-sm text-neutral-600">
            Students only see the programme tracks switched on below. Content under a hidden track stays
            in the catalogue but disappears from browsing, filters, and search until you re-enable it.
            Toggling applies immediately.
        </p>

        <div class="mt-4 space-y-2">
            @forelse ($programmes as $programme)
                <div class="flex items-center justify-between gap-4 rounded-xl border border-neutral-200 bg-white px-4 py-3 transition-colors hover:border-brand-300 dark:!bg-neutral-900">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-neutral-900">{{ $programme->name }}</span>
                        <span class="mt-0.5 block text-xs text-neutral-500">
                            {{ $programme->slug }} · visible to students:
                            <span class="{{ $programme->enabled ? 'font-semibold text-green-700' : 'font-semibold text-red-600' }}">
                                {{ $programme->enabled ? 'Yes' : 'No' }}
                            </span>
                        </span>
                    </span>
                    <form method="POST" action="{{ route('admin.settings.toggle', $programme) }}" class="shrink-0">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $programme->enabled ? '0' : '1' }}">
                        <button type="submit" aria-label="{{ $programme->enabled ? 'Hide' : 'Show' }} {{ $programme->name }}"
                                title="{{ $programme->enabled ? 'Click to hide from students' : 'Click to show to students' }}"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $programme->enabled ? 'bg-brand-600' : 'bg-neutral-300' }}">
                            <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform {{ $programme->enabled ? 'translate-x-5' : '' }}"></span>
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-neutral-500">No programmes yet — add the first track below.</p>
            @endforelse
        </div>
    </section>

    {{-- ── Add a programme track ──────────────────────────────────── --}}
    <form method="POST" action="{{ route('admin.settings.store') }}" class="card mt-5 max-w-2xl p-5">
        @csrf
        <h2 class="text-base font-semibold text-neutral-900">Add a programme</h2>
        <p class="mt-1 text-sm text-neutral-600">
            New tracks appear in the student browse flow (programme → level → semester) as soon as they
            are saved and enabled.
        </p>
        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label class="label" for="new-programme-name">Full name</label>
                <input id="new-programme-name" name="name" class="input" placeholder="e.g. Bachelor of Science in IT"
                       maxlength="120" required>
            </div>
            <button type="submit" class="btn-secondary">Add programme</button>
        </div>
        <p class="mt-2 text-xs text-neutral-500">
            The URL slug is generated from the name (e.g. “bachelor-of-science-in-it”).
        </p>
    </form>

    {{-- ── Remove a programme track ───────────────────────────────── --}}
    <section class="card mt-5 max-w-2xl p-5">
        <h2 class="text-base font-semibold text-neutral-900">Remove a programme</h2>
        <p class="mt-1 text-sm text-neutral-600">
            A track can only be deleted once it has no bundles or papers attached. Tracks you merely want
            off the site can simply be toggled off above — content stays but is hidden from students.
        </p>
        @if ($programmes->isEmpty())
            <p class="mt-4 text-sm text-neutral-500">No programmes to remove.</p>
        @else
            <div class="mt-4 space-y-2">
                @foreach ($programmes as $programme)
                    @php $canDelete = $deletable($programme); @endphp
                    <div class="flex items-center justify-between gap-4 rounded-xl border px-4 py-3 {{ $canDelete ? 'border-neutral-200 bg-white dark:!bg-neutral-900' : 'border-neutral-100 bg-neutral-50 dark:!bg-neutral-900/50' }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-neutral-900">{{ $programme->name }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">
                                @if ($canDelete)
                                    No bundles or papers — safe to delete
                                @else
                                    {{ ($counts[$programme->id]['bundles'] ?? 0) }} bundle(s) ·
                                    {{ ($counts[$programme->id]['materials'] ?? 0) }} paper(s) attached
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" class="btn-secondary btn-sm" title="Rename {{ $programme->name }}"
                                    onclick="openRenameModal({{ $programme->id }}, @js($programme->name))">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.settings.destroy', $programme) }}" class="inline"
                                  onsubmit="return confirm('Delete “{{ $programme->name }}” permanently? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm" @disabled(! $canDelete)
                                        title="{{ $canDelete ? 'Delete '.$programme->name.' permanently' : $programme->name.' still has content — unpublish or move it first' }}">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Rename modal (port of EditProgrammeModal) ──────────────── --}}
    <div id="rename-backdrop" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
         onclick="if (event.target === this) closeRenameModal()">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl dark:!bg-neutral-900">
            <h3 class="text-lg font-semibold text-neutral-900">Rename programme</h3>
            <p class="mt-2 text-sm text-neutral-600">Current name: <span id="rename-current" class="font-medium"></span></p>
            <form method="POST" id="rename-form" class="mt-4">
                <input id="rename-name" name="name" class="input w-full text-center" maxlength="120" required autofocus>
            </form>
            <div class="mt-5 flex justify-center gap-3">
                <button type="button" onclick="closeRenameModal()"
                        class="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:!border-neutral-700 dark:!text-neutral-300">
                    Cancel
                </button>
                <button type="submit" form="rename-form"
                        class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Save
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function openRenameModal(slug, currentName) {
                document.getElementById('rename-current').textContent = currentName;
                document.getElementById('rename-name').value = currentName;
                var form = document.getElementById('rename-form');
                form.action = '/admin/settings/' + encodeURIComponent(slug) + '/rename';
                // Fresh CSRF token per submission.
                var token = document.querySelector('meta[name="csrf-token"]');
                var input = form.querySelector('input[name="_token"]');
                if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = '_token'; form.appendChild(input); }
                input.value = token ? token.content : '';
                var backdrop = document.getElementById('rename-backdrop');
                backdrop.classList.remove('hidden');
                backdrop.classList.add('flex');
                document.getElementById('rename-name').focus();
            }
            function closeRenameModal() {
                var backdrop = document.getElementById('rename-backdrop');
                backdrop.classList.add('hidden');
                backdrop.classList.remove('flex');
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeRenameModal();
            });
        </script>
    @endpush
@endsection
