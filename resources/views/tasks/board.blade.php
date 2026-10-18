<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">
        Drag tasks between columns or use their status menus.
    </p>

    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-500">
        Up to 20 tasks per column page
    </span>
</div>

<p data-board-message role="status" aria-live="polite" aria-atomic="true" class="mb-4 text-sm font-medium text-slate-700">
</p>

<div data-task-board
    class="flex items-start gap-5 overflow-x-auto rounded-2xl pb-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
    role="region" aria-label="Task board" tabindex="0">
    @foreach ($columns as $column)
        @php
            $columnStatus = $column['status'];
            $columnTasks = $column['tasks'];
        @endphp

        <section data-board-column="{{ $columnStatus->value }}" aria-labelledby="column-{{ $columnStatus->value }}"
            class="w-80 shrink-0 rounded-2xl border border-slate-200 bg-slate-100/70 p-3">
            <header class="mb-4 flex items-center justify-between gap-3 px-1 pt-1">
                <h2 id="column-{{ $columnStatus->value }}">
                    <x-tasks.badge :value="$columnStatus" />
                </h2>

                <span
                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ $columnTasks->total() }}
                    <span class="sr-only">matching tasks</span>
                </span>
            </header>

            <div class="space-y-3">
                @forelse ($columnTasks as $task)
                    <article data-task-card data-task-id="{{ $task->id }}"
                        data-task-status="{{ $task->status->value }}" data-task-version="{{ $task->lock_version }}"
                        aria-labelledby="task-{{ $task->id }}"
                        class="ui-card p-4 transition-colors hover:border-indigo-300">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <x-tasks.badge :value="$task->priority" />

                            <button type="button" data-task-drag-handle hidden
                                aria-label="Move {{ $task->reference }}. Drag with a mouse or click to focus its status menu."
                                class="cursor-grab rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 active:cursor-grabbing">
                                Drag
                            </button>
                        </div>

                        <h3 id="task-{{ $task->id }}">
                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                class="group block rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                                <span class="font-mono text-xs font-semibold text-indigo-700">
                                    {{ $task->reference }}
                                </span>

                                <span
                                    class="mt-2 block break-words text-sm leading-6 font-semibold text-slate-900 group-hover:text-indigo-700">
                                    {{ $task->title }}
                                </span>
                            </a>
                        </h3>

                        <dl class="mt-5 space-y-3 text-xs">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="shrink-0 text-slate-500">Assignee</dt>
                                <dd class="min-w-0 break-words text-right font-medium text-slate-700">
                                    {{ $task->assignee?->name ?? 'Unassigned' }}

                                    @if ($task->assignee && !$task->assignee->is_active)
                                        <span class="block font-normal text-slate-500">Inactive</span>
                                    @endif
                                </dd>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <dt class="shrink-0 text-slate-500">Due date</dt>
                                <dd class="text-right font-medium text-slate-700">
                                    {{ $task->due_date?->format('M j, Y') ?? 'No due date' }}
                                </dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('projects.tasks.status', [$project, $task]) }}"
                            data-task-status-form class="mt-5 border-t border-slate-100 pt-4">
                            @csrf
                            @method('PATCH')

                            <input type="hidden" name="lock_version" value="{{ $task->lock_version }}">

                            <label for="task-status-{{ $task->id }}"
                                class="mb-2 block text-xs font-medium text-slate-600">
                                Move {{ $task->reference }} to
                            </label>

                            <div class="flex gap-2">
                                <select id="task-status-{{ $task->id }}" name="status"
                                    class="ui-control min-w-0 flex-1">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($task->status->value === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>

                                <button type="submit" class="ui-button ui-button-secondary px-3">
                                    Move
                                </button>
                            </div>
                        </form>
                    </article>
                @empty
                    <p
                        class="rounded-xl border border-dashed border-slate-300 bg-white/60 px-5 py-10 text-center text-sm leading-6 text-slate-500">
                        No matching tasks on this page.
                    </p>
                @endforelse
            </div>

            @if ($columnTasks->hasPages())
                <div class="mt-4 border-t border-slate-200 px-1 pt-4">
                    <p class="mb-3 text-xs text-slate-500">
                        Page {{ $columnTasks->currentPage() }} of {{ $columnTasks->lastPage() }}
                    </p>

                    {{ $columnTasks->links('pagination::simple-tailwind') }}
                </div>
            @endif
        </section>
    @endforeach
</div>
