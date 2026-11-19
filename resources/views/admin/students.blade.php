@extends('admin.layout')

@section('title', 'Admin — Students & Users')

@section('admin-content')
    <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Students</h1>
    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
        Manage user accounts, suspend or reactivate access, and grant or revoke administrator privileges.
    </p>

    {{-- Quick grant admin by email --}}
    <div class="card mt-5 max-w-2xl p-5">
        <h2 class="text-sm font-semibold text-neutral-900 dark:text-white">Grant administrator access</h2>
        <p class="mt-0.5 text-xs text-neutral-600 dark:text-neutral-400">
            Promote an existing student to an administrator by email. Administrators have full access to the control center, content, settings, and orders.
        </p>

        <form method="POST" action="{{ route('admin.students.grant-admin') }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="label" for="grant-admin-email">Student email address</label>
                <input id="grant-admin-email" name="email" type="email" required
                       value="{{ old('email') }}"
                       placeholder="student@example.com"
                       class="input py-2 text-sm">
            </div>
            <button type="submit" class="btn-primary"
                    onclick="return confirm('Grant administrator privileges to this user?')">
                Grant admin
            </button>
        </form>
        @error('email')
            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Filter pills & Search --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-1.5">
            <a href="{{ route('admin.students', array_filter(['q' => $q])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ empty($role) ? 'bg-brand-600 text-white' : 'bg-white text-neutral-700 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                All ({{ $totalCount }})
            </a>
            <a href="{{ route('admin.students', array_filter(['role' => 'STUDENT', 'q' => $q])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $role === 'STUDENT' ? 'bg-brand-600 text-white' : 'bg-white text-neutral-700 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                Students ({{ $studentCount }})
            </a>
            <a href="{{ route('admin.students', array_filter(['role' => 'ADMIN', 'q' => $q])) }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $role === 'ADMIN' ? 'bg-brand-600 text-white' : 'bg-white text-neutral-700 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                Admins ({{ $adminCount }})
            </a>
        </div>

        <form method="GET" action="{{ route('admin.students') }}" class="flex items-center gap-2">
            @if ($role)
                <input type="hidden" name="role" value="{{ $role }}">
            @endif
            <input type="search" name="q" value="{{ $q }}" placeholder="Search name or email…" class="input w-56 !py-1.5 text-sm">
            <button type="submit" class="btn-secondary btn-sm">Search</button>
            @if ($q)
                <a href="{{ route('admin.students', array_filter(['role' => $role])) }}" class="text-xs text-neutral-500 hover:underline">Clear</a>
            @endif
        </form>
    </div>

    {{-- Users table --}}
    <div class="card mt-3 md:overflow-x-auto">
        <table class="table-base table-responsive">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Purchases</th>
                    <th>Library items</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td data-label="User">
                            <div>
                                <p class="font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $student->first_name }} {{ $student->last_name }}
                                    @if ($student->id === auth()->id())
                                        <span class="text-xs font-normal text-neutral-400 dark:text-neutral-500">(You)</span>
                                    @endif
                                </p>
                                <p class="text-xs text-neutral-500">{{ $student->email }}</p>
                            </div>
                        </td>
                        <td data-label="Role">
                            @if ($student->isAdmin())
                                <span class="badge-gold font-semibold">Admin</span>
                            @else
                                <span class="badge-neutral">Student</span>
                            @endif
                        </td>
                        <td data-label="Purchases" class="tabular-nums">{{ $student->orders_count }}</td>
                        <td data-label="Library items" class="tabular-nums">{{ $student->entitlements_count }}</td>
                        <td data-label="Status">
                            <span class="{{ $student->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }}">{{ $student->status }}</span>
                        </td>
                        <td data-label="Joined" class="whitespace-nowrap text-neutral-600 dark:text-neutral-400">{{ $student->created_at->format('d/m/Y') }}</td>
                        <td data-label="">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($student->id !== auth()->id())
                                    @if ($student->isAdmin())
                                        <form method="POST" action="{{ route('admin.students.role', $student) }}"
                                              onsubmit="return confirm('Revoke administrator access for {{ $student->email }}?')">
                                            @csrf
                                            <input type="hidden" name="role" value="STUDENT">
                                            <button type="submit" class="btn-secondary btn-sm" title="Revoke admin privileges">
                                                Revoke admin
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.students.role', $student) }}"
                                              onsubmit="return confirm('Grant administrator access to {{ $student->email }}?')">
                                            @csrf
                                            <input type="hidden" name="role" value="ADMIN">
                                            <button type="submit" class="btn-secondary btn-sm !text-brand-700 dark:!text-brand-300" title="Grant administrator access">
                                                Make admin
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.students.status', $student) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $student->status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE' }}">
                                            <button class="{{ $student->status === 'ACTIVE' ? 'btn-danger btn-sm' : 'btn-secondary btn-sm' }}">
                                                {{ $student->status === 'ACTIVE' ? 'Suspend' : 'Reactivate' }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-neutral-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->links() }}</div>
@endsection
