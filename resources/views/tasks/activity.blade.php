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

<section id="activity" aria-labelledby="activity-heading" class="ui-card mt-6 scroll-mt-24 p-6 sm:p-8">
    <h2 id="activity-heading" class="text-lg font-semibold text-slate-900">
        Activity ({{ $activities->total() }})
    </h2>

    <p class="mt-1 text-sm text-slate-500">
        Newest first. Times are shown in UTC.
    </p>

    <ol class="mt-6 space-y-6 pl-1">
        @forelse ($activities as $activity)
            <li class="relative min-w-0 border-l-2 border-indigo-100 pb-2 pl-5 sm:pl-6">
                <span aria-hidden="true"
                    class="absolute top-1.5 -left-[6px] size-2.5 rounded-full bg-indigo-500 ring-4 ring-white">
                </span>

                <p class="break-words text-sm leading-6 text-slate-600">
                    <span class="font-semibold text-slate-900">
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
                            <div class="min-w-0 overflow-hidden rounded-xl border border-slate-200">
                                <dt
                                    class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800">
                                    {{ $activityLabels[$field] ?? $field }}
                                </dt>

                                <dd class="grid gap-4 p-4 text-sm sm:grid-cols-2">
                                    <div class="min-w-0 rounded-lg bg-slate-50 p-3">
                                        <span class="mb-2 block text-xs font-medium text-slate-500">
                                            Previous value
                                        </span>

                                        <x-tasks.activity-value :field="$field" :value="$change['old']" :assignee-names="$activityAssigneeNames" />
                                    </div>

                                    <div class="min-w-0 rounded-lg bg-indigo-50 p-3">
                                        <span class="mb-2 block text-xs font-medium text-indigo-700">
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
            <li class="rounded-xl border border-dashed border-slate-200 px-5 py-10 text-center text-sm text-slate-500">
                No activity recorded yet. New task changes will appear here.
            </li>
        @endforelse
    </ol>

    @if ($activities->hasPages())
        <div class="mt-6">
            {{ $activities->links() }}
        </div>
    @endif
</section>
