<x-layouts.app title="Dashboard">
    <p class="mb-6 text-slate-600">
        Welcome back, {{ auth()->user()->name }}.
    </p>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
        'open' => 'Open assigned tasks',
        'overdue' => 'Overdue',
        'due_today' => 'Due today',
        'projects' => 'Accessible projects',
    ] as $key => $label)
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <dt class="text-sm text-slate-600">{{ $label }}</dt>
                <dd class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ $summary[$key] }}
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="mt-4 flex flex-wrap gap-4 text-sm">
        <a href="{{ route('tasks.mine', ['due' => 'overdue']) }}" class="rounded text-indigo-700 underline">
            View overdue tasks
        </a>

        <a href="{{ route('tasks.mine', ['due' => 'today']) }}" class="rounded text-indigo-700 underline">
            View tasks due today
        </a>

        <a href="{{ route('projects.index') }}" class="rounded text-indigo-700 underline">
            View projects
        </a>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        Task counts cover your assignments in accessible projects.
        Deadline counts exclude completed tasks and use the UTC calendar.
    </p>

    <section aria-labelledby="status-heading" class="mt-8">
        <h2 id="status-heading" class="mb-4 text-lg font-semibold">
            Your assignments by status
        </h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($taskStatuses as $status)
                <a href="{{ route('tasks.mine', ['status' => $status->value]) }}"
                    class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 hover:border-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    <x-tasks.badge :value="$status" />

                    <span class="text-xl font-semibold">
                        {{ $statusCounts[$status->value] }}
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="upcoming-heading" class="mt-8">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 id="upcoming-heading" class="text-lg font-semibold">
                Open tasks due first
            </h2>

            <a href="{{ route('tasks.mine') }}" class="rounded text-sm text-indigo-700 underline">
                View all my tasks
            </a>
        </div>

        <p class="mb-4 text-sm text-slate-600">
            Up to five open tasks. Tasks without a due date appear last.
        </p>

        @include('tasks.list', [
            'showProject' => true,
            'caption' => 'Open assigned tasks ordered by due date',
            'emptyMessage' => 'No open tasks assigned to you.',
        ])
    </section>
</x-layouts.app>
