@props(['summary', 'taskRoute', 'routeParameters' => [], 'id' => 'task-progress'])

@php
    $metrics = [
        'All tasks' => [
            'count' => $summary['total'],
            'filters' => [],
            'tone' => 'indigo',
        ],
        'Completed' => [
            'count' => $summary['done'],
            'filters' => ['status' => \App\Enums\TaskStatus::Done->value],
            'tone' => 'emerald',
        ],
        'Overdue' => [
            'count' => $summary['overdue'],
            'filters' => ['due' => 'overdue'],
            'tone' => 'rose',
        ],
        'Due today' => [
            'count' => $summary['due_today'],
            'filters' => ['due' => 'today'],
            'tone' => 'amber',
        ],
    ];
@endphp

<section aria-labelledby="{{ $id }}-heading" {{ $attributes->class(['ui-card p-6 sm:p-8']) }}>
    <h2 id="{{ $id }}-heading" class="text-lg font-semibold text-slate-900">
        Task progress
    </h2>

    <label for="{{ $id }}-bar" class="mt-4 block text-sm text-slate-600">
        {{ $summary['done'] }} of {{ $summary['total'] }} tasks complete
        ({{ $summary['completion_percentage'] }}%)
    </label>

    <progress id="{{ $id }}-bar" value="{{ $summary['done'] }}" max="{{ max(1, $summary['total']) }}"
        class="mt-3 h-3 w-full overflow-hidden rounded-full accent-emerald-600">
        {{ $summary['completion_percentage'] }}%
    </progress>

    @if ($summary['total'] === 0)
        <p class="mt-3 text-sm text-slate-500">
            No tasks yet.
        </p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($metrics as $label => $metric)
            <a href="{{ route($taskRoute, array_merge($routeParameters, $metric['filters'])) }}"
                class="ui-card ui-stat ui-stat-{{ $metric['tone'] }}">
                <span class="flex items-center gap-2 text-sm text-slate-600">
                    <span aria-hidden="true" class="ui-stat-dot"></span>
                    {{ $label }}
                </span>

                <span class="mt-3 block text-3xl font-semibold tracking-tight text-slate-900">
                    {{ number_format($metric['count']) }}
                </span>

                <span class="ui-stat-hint">
                    View tasks <span aria-hidden="true">→</span>
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-3 border-t border-slate-100 pt-6 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (\App\Enums\TaskStatus::cases() as $status)
            <a href="{{ route($taskRoute, array_merge($routeParameters, ['status' => $status->value])) }}"
                class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 transition-colors hover:border-indigo-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                <x-tasks.badge :value="$status" />

                <span class="text-sm font-semibold text-slate-800">
                    {{ number_format($summary['status_counts'][$status->value]) }}
                </span>
            </a>
        @endforeach
    </div>

    <p class="mt-5 text-xs leading-5 text-slate-500">
        Due dates use the UTC calendar. Completed tasks are excluded from deadline counts.
    </p>
</section>
