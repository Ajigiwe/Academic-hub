{{-- ── Create Bundle Modal ────────────────────────────────────── --}}
<div id="new-bundle-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
     onclick="if (event.target === this) closeNewBundleModal()">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:!bg-neutral-900 border border-neutral-200 dark:border-neutral-800">
        <div class="flex items-center justify-between border-b border-neutral-100 pb-3 dark:border-neutral-800">
            <div>
                <h3 class="text-base font-bold text-neutral-900 dark:text-white">Create New Bundle</h3>
                <p class="text-xs text-neutral-500">A bundle groups past papers for a course &amp; academic year.</p>
            </div>
            <button type="button" onclick="closeNewBundleModal()" class="rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.bundles.store') }}" class="mt-4 space-y-4">
            @csrf

            {{-- Programme --}}
            <div>
                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Programme</label>
                @php
                    $modalProgrammes = \App\Models\Programme::orderBy('name')->get();
                @endphp
                @if ($modalProgrammes->isNotEmpty())
                    <select name="programme_id" id="modal-programme-select" class="input mt-1 w-full text-sm">
                        @foreach ($modalProgrammes as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="mt-1.5 flex items-center justify-between text-[11px] text-neutral-500">
                        <span>Or enter a new programme:</span>
                    </div>
                @endif
                <input type="text" name="programme_name" placeholder="e.g. Nursing or Procurement"
                       class="input mt-1 w-full text-sm" {{ $modalProgrammes->isEmpty() ? 'required' : '' }}>
            </div>

            {{-- Course Code and Course Title --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Course Code</label>
                    <input type="text" name="course_code" required placeholder="e.g. NURS 101" maxlength="30"
                           class="input mt-1 w-full text-sm uppercase">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Course Title</label>
                    <input type="text" name="course_title" required placeholder="e.g. Anatomy and Physiology"
                           class="input mt-1 w-full text-sm">
                </div>
            </div>

            {{-- Academic Year and Level --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Academic Year</label>
                    <input type="text" name="academic_year" required
                           value="{{ date('Y').'/'.(date('Y')+1) }}"
                           placeholder="2024/2025"
                           class="input mt-1 w-full text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Level</label>
                    <select name="level" required class="input mt-1 w-full text-sm">
                        <option value="100">Level 100</option>
                        <option value="200">Level 200</option>
                        <option value="300">Level 300</option>
                        <option value="400">Level 400</option>
                    </select>
                </div>
            </div>

            {{-- Optional Title override --}}
            <div>
                <label class="block text-xs font-semibold text-neutral-700 dark:text-neutral-300">Bundle Title <span class="font-normal text-neutral-400">(optional)</span></label>
                <input type="text" name="title" placeholder="Leave blank to auto-generate from course and year"
                       class="input mt-1 w-full text-sm">
            </div>

            <div class="mt-5 flex justify-end gap-2.5 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <button type="button" onclick="closeNewBundleModal()"
                        class="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800">
                    Cancel
                </button>
                <button type="submit"
                        class="btn-primary rounded-xl px-5 py-2 text-sm font-semibold text-white">
                    Create Bundle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openNewBundleModal() {
        var m = document.getElementById('new-bundle-modal');
        if (m) {
            m.classList.remove('hidden');
            m.classList.add('flex');
            var first = m.querySelector('input[name="course_code"]');
            if (first) first.focus();
        }
    }
    function closeNewBundleModal() {
        var m = document.getElementById('new-bundle-modal');
        if (m) {
            m.classList.add('hidden');
            m.classList.remove('flex');
        }
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNewBundleModal();
    });
</script>
