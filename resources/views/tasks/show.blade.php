<x-layouts.app :title="$task->reference . ' · ' . $task->title">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('projects.tasks.index', $project) }}" class="rounded text-sm text-indigo-700 underline">
            Back to tasks
        </a>

        @can('update', $task)
            <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Edit task
            </a>
        @endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="description-heading"
            class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <div class="mb-6 flex flex-wrap gap-2">
                <x-tasks.badge :value="$task->status" />
                <x-tasks.badge :value="$task->priority" />
            </div>

            <h2 id="description-heading" class="text-lg font-semibold">Description</h2>

            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">
                {{ $task->description ?: 'No description provided.' }}</p>
        </section>

        <section aria-labelledby="details-heading" class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 id="details-heading" class="text-lg font-semibold">Task details</h2>

            <dl class="mt-5 space-y-5 text-sm">
                <div>
                    <dt class="text-slate-500">Project</dt>
                    <dd class="mt-1 break-words font-medium">{{ $project->name }}</dd>
                </div>

                <div>
                    <dt class="text-slate-500">Assignee</dt>
                    <dd class="mt-1 break-words font-medium">
                        {{ $task->assignee?->name ?? 'Unassigned' }}

                        @if ($task->assignee && !$task->assignee->is_active)
                            <span class="ml-1 text-xs font-normal text-slate-500">Inactive</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">Due date</dt>
                    <dd class="mt-1 font-medium">
                        {{ $task->due_date?->format('M j, Y') ?? 'No due date' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">Created by</dt>
                    <dd class="mt-1 break-words font-medium">{{ $task->creator->name }}</dd>
                </div>

                <div>
                    <dt class="text-slate-500">Created (UTC)</dt>
                    <dd class="mt-1 font-medium">
                        <time datetime="{{ $task->created_at->toIso8601String() }}">
                            {{ $task->created_at->format('M j, Y H:i') }}
                        </time>
                    </dd>
                </div>

                @if ($task->completed_at)
                    <div>
                        <dt class="text-slate-500">Completed (UTC)</dt>
                        <dd class="mt-1 font-medium">
                            <time datetime="{{ $task->completed_at->toIso8601String() }}">
                                {{ $task->completed_at->format('M j, Y H:i') }}
                            </time>
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>
    @include('tasks.comments')
</x-layouts.app>
