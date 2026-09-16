@extends('admin.layout')

@section('title', 'Admin — Students')

@section('admin-content')
    <h1 class="text-xl font-bold text-neutral-900">Students</h1>
    <p class="mt-1 text-sm text-neutral-600">
        Suspended users cannot log in and lose viewer access immediately (enforced server-side on every
        session check).
    </p>

    <form method="GET" class="mt-4 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Name or email…" class="input w-56 !py-1.5 text-sm">
        <button class="btn-secondary btn-sm">Search</button>
    </form>

    <div class="card mt-5 md:overflow-x-auto">
        <table class="table-base table-responsive">
            <thead>
                <tr>
                    <th>Student</th>
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
                        <td data-label="Student">
                            <div class="md:text-right">
                                <p class="font-medium">
                                    {{ $student->first_name }} {{ $student->last_name }}
                                    @if ($student->role === 'ADMIN')
                                        <span class="badge-gold ml-2">admin</span>
                                    @endif
                                </p>
                                <p class="text-xs text-neutral-500">{{ $student->email }}</p>
                            </div>
                        </td>
                        <td data-label="Purchases" class="tabular-nums">{{ $student->orders_count }}</td>
                        <td data-label="Library items" class="tabular-nums">{{ $student->entitlements_count }}</td>
                        <td data-label="Status">
                            <span class="{{ $student->status === 'ACTIVE' ? 'badge-success' : 'badge-danger' }}">{{ $student->status }}</span>
                        </td>
                        <td data-label="Joined" class="whitespace-nowrap text-neutral-600">{{ $student->created_at->format('d/m/Y') }}</td>
                        <td data-label="">
                            @if ($student->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.students.status', $student) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $student->status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE' }}">
                                    <button class="{{ $student->status === 'ACTIVE' ? 'btn-danger btn-sm' : 'btn-secondary btn-sm' }}">
                                        {{ $student->status === 'ACTIVE' ? 'Suspend' : 'Reactivate' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-neutral-500">No students.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $students->links() }}</div>
@endsection
