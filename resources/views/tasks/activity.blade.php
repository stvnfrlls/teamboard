@php
    $activityLabels = [
        'title' => 'Title',
        'description' => 'Description',
        'status' => 'Status',
        'priority' => 'Priority',
        'assignee_id' => 'Assignee',
        'due_date' => 'Due date',
    ];
@endphp

<section id="activity" aria-labelledby="activity-heading" class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
    <h2 id="activity-heading" class="text-lg font-semibold">
        Activity ({{ $activities->total() }})
    </h2>

    <p class="mt-2 text-sm text-slate-500">
        Newest first. Times are shown in UTC.
    </p>

    <ol class="mt-5 divide-y divide-slate-100">
        @forelse ($activities as $activity)
            <li class="py-5">
                <p class="break-words text-sm text-slate-700">
                    <span class="font-semibold">
                        {{ $activity->actor?->name ?? 'Unknown user' }}
                    </span>

                    {{ $activity->event === 'created' ? 'created this task.' : 'updated this task.' }}
                </p>

                <time datetime="{{ $activity->created_at->toIso8601String() }}"
                    class="mt-1 block text-xs text-slate-500">
                    {{ $activity->created_at->format('M j, Y H:i') }} UTC
                </time>

                @if ($activity->changes !== [])
                    <dl class="mt-4 space-y-4">
                        @foreach ($activity->changes as $field => $change)
                            <div class="rounded-lg border border-slate-200 p-4">
                                <dt class="text-sm font-semibold text-slate-800">
                                    {{ $activityLabels[$field] ?? $field }}
                                </dt>

                                <dd class="mt-3 grid gap-4 text-sm sm:grid-cols-2">
                                    <div class="min-w-0">
                                        <span class="mb-1 block text-xs font-medium text-slate-500">
                                            Previous value
                                        </span>

                                        <x-tasks.activity-value :field="$field" :value="$change['old']" :assignee-names="$activityAssigneeNames" />
                                    </div>

                                    <div class="min-w-0">
                                        <span class="mb-1 block text-xs font-medium text-slate-500">
                                            New value
                                        </span>

                                        <x-tasks.activity-value :field="$field" :value="$change['new']" :assignee-names="$activityAssigneeNames" />
                                    </div>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </li>
        @empty
            <li class="py-5 text-sm text-slate-500">
                No activity recorded yet. New task changes will appear here.
            </li>
        @endforelse
    </ol>

    {{ $activities->links() }}
</section>
