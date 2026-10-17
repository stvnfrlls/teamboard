<p class="mb-4 text-sm text-slate-600">
    Drag tasks between columns or use their status menus. Each column shows up to 20 tasks per page.
</p>

<p data-board-message role="status" aria-live="polite" aria-atomic="true" class="mb-4 text-sm font-medium text-slate-700">
</p>

<div data-task-board class="flex items-start gap-4 overflow-x-auto pb-4" role="region" aria-label="Task board"
    tabindex="0">
    @foreach ($columns as $column)
        @php
            $columnStatus = $column['status'];
            $columnTasks = $column['tasks'];
        @endphp

        <section data-board-column="{{ $columnStatus->value }}" aria-labelledby="column-{{ $columnStatus->value }}"
            class="w-80 shrink-0 rounded-xl border border-slate-200 bg-slate-100 p-3">
            <header class="mb-4 flex items-center justify-between gap-3 px-1">
                <h2 id="column-{{ $columnStatus->value }}" class="font-semibold">
                    {{ $columnStatus->label() }}
                </h2>

                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600">
                    {{ $columnTasks->total() }}
                    <span class="sr-only">matching tasks</span>
                </span>
            </header>

            <div class="space-y-3">
                @forelse ($columnTasks as $task)
                    <article data-task-card data-task-id="{{ $task->id }}"
                        data-task-status="{{ $task->status->value }}" data-task-version="{{ $task->lock_version }}"
                        aria-labelledby="task-{{ $task->id }}"
                        class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <button type="button" data-task-drag-handle hidden
                            aria-label="Move {{ $task->reference }}. Drag with a mouse or click to focus its status menu."
                            class="mb-3 cursor-grab rounded border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 active:cursor-grabbing">
                            Drag
                        </button>
                        <h3 id="task-{{ $task->id }}">
                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                class="block rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <span class="font-mono text-xs font-semibold text-indigo-700">
                                    {{ $task->reference }}
                                </span>

                                <span class="mt-2 block break-words text-sm font-semibold text-slate-900">
                                    {{ $task->title }}
                                </span>
                            </a>
                        </h3>

                        <div class="mt-3">
                            <x-tasks.badge :value="$task->priority" />
                        </div>

                        <p class="mt-3 break-words text-xs text-slate-600">
                            {{ $task->assignee?->name ?? 'Unassigned' }}

                            @if ($task->assignee && !$task->assignee->is_active)
                                <span>(Inactive)</span>
                            @endif
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            Due: {{ $task->due_date?->format('M j, Y') ?? 'No due date' }}
                        </p>

                        <form method="POST" action="{{ route('projects.tasks.status', [$project, $task]) }}"
                            data-task-status-form class="mt-4 border-t border-slate-100 pt-3">
                            @csrf
                            @method('PATCH')

                            <input type="hidden" name="lock_version" value="{{ $task->lock_version }}">

                            <label for="task-status-{{ $task->id }}"
                                class="mb-2 block text-xs font-medium text-slate-700">
                                Move {{ $task->reference }} to
                            </label>

                            <div class="flex gap-2">
                                <select id="task-status-{{ $task->id }}" name="status"
                                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600">
                                    @foreach ($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($task->status->value === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>

                                <button type="submit"
                                    class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Move
                                </button>
                            </div>
                        </form>
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500">
                        No matching tasks on this page.
                    </p>
                @endforelse
            </div>

            @if ($columnTasks->hasPages())
                <div class="mt-4">
                    <p class="mb-3 text-xs text-slate-600">
                        Page {{ $columnTasks->currentPage() }} of {{ $columnTasks->lastPage() }}
                    </p>

                    {{ $columnTasks->links('pagination::simple-tailwind') }}
                </div>
            @endif
        </section>
    @endforeach
</div>
