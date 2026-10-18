<div class="ui-table-frame ui-card overflow-x-auto">
    <table role="table" class="ui-responsive-table min-w-full text-left text-sm">
        <caption class="sr-only">
            {{ $caption ?? (isset($project) ? 'Tasks for ' . $project->name : 'Tasks') }}
        </caption>

        <thead role="rowgroup" class="sr-only bg-slate-50 text-xs tracking-wide text-slate-500 uppercase md:not-sr-only">
            <tr role="row">
                <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Reference</th>

                @if ($showProject ?? false)
                    <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Project</th>
                @endif

                <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Task</th>
                <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Status</th>
                <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Priority</th>
                <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Assignee</th>
                <th role="columnheader" scope="col" class="whitespace-nowrap px-5 py-4 font-semibold">
                    Due date
                </th>
                <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Actions</th>
            </tr>
        </thead>

        <tbody role="rowgroup" class="md:divide-y md:divide-slate-100">
            @forelse ($tasks as $task)
                <tr role="row" class="transition-colors hover:bg-slate-50/80">
                    <th role="rowheader" scope="row"
                        class="font-mono text-xs font-semibold md:whitespace-nowrap md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Reference</span>

                        <div class="ui-cell-value">
                            <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="ui-link">
                                {{ $task->reference }}
                            </a>
                        </div>
                    </th>

                    @if ($showProject ?? false)
                        <td role="cell" class="md:min-w-40 md:max-w-xs md:px-5 md:py-5">
                            <span aria-hidden="true" class="ui-table-label">Project</span>

                            <div class="ui-cell-value">
                                <a href="{{ route('projects.show', $task->project) }}" class="ui-link">
                                    {{ $task->project->name }}
                                </a>
                            </div>
                        </td>
                    @endif

                    <td role="cell" class="font-medium text-slate-900 md:min-w-56 md:max-w-sm md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Task</span>

                        <div class="ui-cell-value">
                            {{ $task->title }}
                        </div>
                    </td>

                    <td role="cell" class="md:whitespace-nowrap md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Status</span>

                        <div class="ui-cell-value">
                            <x-tasks.badge :value="$task->status" />
                        </div>
                    </td>

                    <td role="cell" class="md:whitespace-nowrap md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Priority</span>

                        <div class="ui-cell-value">
                            <x-tasks.badge :value="$task->priority" />
                        </div>
                    </td>

                    <td role="cell" class="text-slate-700 md:min-w-36 md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Assignee</span>

                        <div class="ui-cell-value">
                            {{ $task->assignee?->name ?? 'Unassigned' }}

                            @if ($task->assignee && !$task->assignee->is_active)
                                <span class="mt-1 block text-xs text-slate-500">Inactive</span>
                            @endif
                        </div>
                    </td>

                    <td role="cell" class="text-slate-500 md:whitespace-nowrap md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Due date</span>

                        <div class="ui-cell-value">
                            {{ $task->due_date?->format('M j, Y') ?? 'No due date' }}
                        </div>
                    </td>

                    <td role="cell" class="md:px-5 md:py-5">
                        <span aria-hidden="true" class="ui-table-label">Actions</span>

                        <div class="ui-cell-value">
                            <a href="{{ route('projects.tasks.edit', [$task->project, $task]) }}"
                                aria-label="Edit {{ $task->reference }}" class="ui-button ui-button-secondary">
                                Edit
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr role="row">
                    <td role="cell" colspan="{{ $showProject ?? false ? 8 : 7 }}"
                        class="ui-table-empty-cell md:px-6 md:py-16 md:text-center">
                        <p class="font-semibold text-slate-800">No tasks to display</p>

                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                            {{ $emptyMessage ?? 'No tasks found. Adjust your filters or check your project assignments.' }}
                        </p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($tasks instanceof \Illuminate\Contracts\Pagination\Paginator)
    <div class="mt-6">
        {{ $tasks->links() }}
    </div>
@endif
