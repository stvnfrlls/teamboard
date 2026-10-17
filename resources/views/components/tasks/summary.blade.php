@props(['summary', 'taskRoute', 'routeParameters' => [], 'id' => 'task-progress'])

@php
    $metrics = [
        'All tasks' => [
            'count' => $summary['total'],
            'filters' => [],
        ],
        'Completed' => [
            'count' => $summary['done'],
            'filters' => ['status' => \App\Enums\TaskStatus::Done->value],
        ],
        'Overdue' => [
            'count' => $summary['overdue'],
            'filters' => ['due' => 'overdue'],
        ],
        'Due today' => [
            'count' => $summary['due_today'],
            'filters' => ['due' => 'today'],
        ],
    ];
@endphp

<section aria-labelledby="{{ $id }}-heading"
    {{ $attributes->class(['rounded-xl border border-slate-200 bg-white p-6']) }}>
    <h2 id="{{ $id }}-heading" class="text-lg font-semibold">
        Task progress
    </h2>

    <label for="{{ $id }}-bar" class="mt-4 block text-sm text-slate-600">
        {{ $summary['done'] }} of {{ $summary['total'] }} tasks complete
        ({{ $summary['completion_percentage'] }}%)
    </label>

    <progress id="{{ $id }}-bar" value="{{ $summary['done'] }}" max="{{ max(1, $summary['total']) }}"
        class="mt-3 h-3 w-full accent-emerald-600">
        {{ $summary['completion_percentage'] }}%
    </progress>

    @if ($summary['total'] === 0)
        <p class="mt-3 text-sm text-slate-500">
            No tasks yet.
        </p>
    @endif

    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($metrics as $label => $metric)
            <a href="{{ route($taskRoute, array_merge($routeParameters, $metric['filters'])) }}"
                class="rounded-lg border border-slate-200 p-4 hover:border-indigo-300 hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                <span class="block text-sm text-slate-600">{{ $label }}</span>
                <span class="mt-2 block text-2xl font-semibold">
                    {{ number_format($metric['count']) }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (\App\Enums\TaskStatus::cases() as $status)
            <a href="{{ route($taskRoute, array_merge($routeParameters, ['status' => $status->value])) }}"
                class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                <x-tasks.badge :value="$status" />

                <span class="text-sm font-semibold">
                    {{ number_format($summary['status_counts'][$status->value]) }}
                </span>
            </a>
        @endforeach
    </div>

    <p class="mt-5 text-xs leading-5 text-slate-500">
        Due dates use the UTC calendar. Completed tasks are excluded from deadline counts.
    </p>
</section>
