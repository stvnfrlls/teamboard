<x-layouts.app :title="$task->reference . ' · ' . $task->title">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('projects.tasks.index', $project) }}" class="ui-button ui-button-secondary">
            <span aria-hidden="true">←</span>
            Back to tasks
        </a>

        @can('update', $task)
            <a href="{{ route('projects.tasks.edit', [$project, $task]) }}" class="ui-button ui-button-primary">
                Edit task
            </a>
        @endcan
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <section aria-labelledby="description-heading" class="ui-card min-w-0 p-6 sm:p-8 xl:col-span-2">
            <div class="mb-6 flex flex-wrap items-center gap-2 border-b border-slate-100 pb-6">
                <span class="mr-auto font-mono text-xs font-semibold text-slate-500">
                    {{ $task->reference }}
                </span>

                <x-tasks.badge :value="$task->status" />
                <x-tasks.badge :value="$task->priority" />
            </div>

            <h2 id="description-heading" class="text-lg font-semibold text-slate-900">
                Description
            </h2>

            <p class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">
                {{ $task->description ?: 'No description provided.' }}</p>
        </section>

        <section aria-labelledby="details-heading" class="ui-card min-w-0 p-6">
            <h2 id="details-heading" class="text-base font-semibold text-slate-900">
                Task details
            </h2>

            <dl class="mt-3 divide-y divide-slate-100 text-sm">
                <div class="py-4">
                    <dt class="text-xs font-medium text-slate-500">Project</dt>
                    <dd class="mt-2 break-words">
                        <a href="{{ route('projects.show', $project) }}" class="ui-link">
                            {{ $project->name }}
                        </a>
                    </dd>
                </div>

                <div class="py-4">
                    <dt class="text-xs font-medium text-slate-500">Assignee</dt>
                    <dd class="mt-2 break-words font-medium text-slate-800">
                        {{ $task->assignee?->name ?? 'Unassigned' }}

                        @if ($task->assignee && !$task->assignee->is_active)
                            <span class="ml-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-normal text-slate-500">
                                Inactive
                            </span>
                        @endif
                    </dd>
                </div>

                <div class="py-4">
                    <dt class="text-xs font-medium text-slate-500">Due date</dt>
                    <dd class="mt-2 font-medium text-slate-800">
                        {{ $task->due_date?->format('M j, Y') ?? 'No due date' }}
                    </dd>
                </div>

                <div class="py-4">
                    <dt class="text-xs font-medium text-slate-500">Created by</dt>
                    <dd class="mt-2 break-words font-medium text-slate-800">
                        {{ $task->creator->name }}
                    </dd>
                </div>

                <div class="py-4">
                    <dt class="text-xs font-medium text-slate-500">Created (UTC)</dt>
                    <dd class="mt-2 text-slate-700">
                        <time datetime="{{ $task->created_at->toIso8601String() }}">
                            {{ $task->created_at->format('M j, Y H:i') }}
                        </time>
                    </dd>
                </div>

                @if ($task->completed_at)
                    <div class="py-4">
                        <dt class="text-xs font-medium text-slate-500">Completed (UTC)</dt>
                        <dd class="mt-2 text-slate-700">
                            <time datetime="{{ $task->completed_at->toIso8601String() }}">
                                {{ $task->completed_at->format('M j, Y H:i') }}
                            </time>
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    @include('tasks.attachments')
    @include('tasks.checklist')
    @include('tasks.comments')
    @include('tasks.activity')
</x-layouts.app>
