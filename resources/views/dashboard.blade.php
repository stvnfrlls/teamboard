<x-layouts.app title="Dashboard">
    @php
        $metrics = [
            [
                'key' => 'open',
                'label' => 'Open assigned tasks',
                'hint' => 'View all my tasks',
                'url' => route('tasks.mine'),
                'tone' => 'indigo',
            ],
            [
                'key' => 'overdue',
                'label' => 'Overdue',
                'hint' => 'View overdue tasks',
                'url' => route('tasks.mine', ['due' => 'overdue']),
                'tone' => 'rose',
            ],
            [
                'key' => 'due_today',
                'label' => 'Due today',
                'hint' => 'View tasks due today',
                'url' => route('tasks.mine', ['due' => 'today']),
                'tone' => 'amber',
            ],
            [
                'key' => 'projects',
                'label' => 'Accessible projects',
                'hint' => 'View projects',
                'url' => route('projects.index'),
                'tone' => 'emerald',
            ],
        ];
    @endphp

    <section class="ui-card mb-5 p-5 sm:mb-6 sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-4 sm:gap-6">
            <div class="min-w-0">
                <p class="mb-2 text-xs font-semibold tracking-widest text-indigo-600 uppercase">
                    Your workspace
                </p>

                <h2 class="break-words text-xl font-semibold tracking-tight sm:text-2xl">
                    Welcome back, {{ auth()->user()->name }}.
                </h2>

                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Track your assignments, deadlines, and project work.
                </p>
            </div>

            <a href="{{ route('tasks.mine') }}" class="ui-button ui-button-primary">
                View my tasks
            </a>
        </div>
    </section>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ($metrics as $metric)
            <a href="{{ $metric['url'] }}"
                class="ui-card ui-stat ui-stat-{{ $metric['tone'] }} flex min-w-0 flex-col p-4 sm:p-5">
                <div class="flex min-h-10 items-start justify-between gap-2 sm:min-h-0">
                    <span class="min-w-0 text-xs leading-5 font-medium text-slate-500 sm:text-sm">
                        {{ $metric['label'] }}
                    </span>

                    <span aria-hidden="true" class="ui-stat-dot mt-1.5"></span>
                </div>

                <span class="mt-3 mb-3 block break-all text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ number_format($summary[$metric['key']]) }}
                </span>

                <span class="ui-stat-hint mt-auto leading-5">
                    {{ $metric['hint'] }} →
                </span>
            </a>
        @endforeach
    </div>

    <p class="mt-4 text-xs leading-5 text-slate-500">
        Task counts cover your assignments in accessible projects.
        Deadline counts exclude completed tasks and use the UTC calendar.
    </p>

    <section aria-labelledby="status-heading" class="ui-card mt-6 p-4 sm:mt-7 sm:p-6">
        <h2 id="status-heading" class="text-lg font-semibold tracking-tight">
            Your assignments by status
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Select a status to filter your task list.
        </p>

        <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
            @foreach ($taskStatuses as $status)
                <a href="{{ route('tasks.mine', ['status' => $status->value]) }}"
                    class="flex min-w-0 flex-col items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 transition-colors hover:border-indigo-200 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 motion-reduce:transition-none sm:flex-row sm:items-center sm:justify-between sm:p-4">
                    <x-tasks.badge :value="$status" />

                    <span class="text-xl font-semibold text-slate-800">
                        {{ number_format($statusCounts[$status->value]) }}
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="upcoming-heading" class="ui-card mt-6 p-4 sm:mt-7 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 id="upcoming-heading" class="text-lg font-semibold tracking-tight">
                    Open tasks due first
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    Up to five open tasks. Tasks without a due date appear last.
                </p>
            </div>

            <a href="{{ route('tasks.mine') }}" class="ui-button ui-button-secondary">
                View all my tasks
            </a>
        </div>

        <div class="mt-5 min-w-0">
            @include('tasks.list', [
                'showProject' => true,
                'caption' => 'Open assigned tasks ordered by due date',
                'emptyMessage' => 'No open tasks assigned to you.',
            ])
        </div>
    </section>
</x-layouts.app>
