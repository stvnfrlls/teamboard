<x-layouts.app :title="$project->name . ' · Tasks'">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('projects.show', $project) }}" class="rounded text-sm text-indigo-700 underline">
            Back to project
        </a>

        <a href="{{ route('projects.tasks.create', $project) }}"
            class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            Add task
        </a>
    </div>

    <form method="GET" action="{{ route('projects.tasks.index', $project) }}"
        class="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-form.input name="search" label="Search tasks" type="search" :value="$filters['search'] ?? ''" maxlength="100"
            placeholder="Title or task reference" />

        <x-form.select name="status" label="Status" :options="['' => 'All statuses'] + $statuses" :value="$filters['status'] ?? ''" />

        <x-form.select name="priority" label="Priority" :options="['' => 'All priorities'] + $priorities" :value="$filters['priority'] ?? ''" />

        <div class="flex items-end gap-4">
            <button type="submit"
                class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Apply filters
            </button>

            <a href="{{ route('projects.tasks.index', $project) }}"
                class="rounded py-2.5 text-sm text-indigo-700 underline">
                Clear
            </a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <caption class="sr-only">Tasks for {{ $project->name }}</caption>

            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">Reference</th>
                    <th scope="col" class="px-4 py-3 font-medium">Task</th>
                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    <th scope="col" class="px-4 py-3 font-medium">Priority</th>
                    <th scope="col" class="px-4 py-3 font-medium">Assignee</th>
                    <th scope="col" class="px-4 py-3 font-medium">Due date</th>
                    <th scope="col" class="px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse ($tasks as $task)
                    <tr>
                        <th scope="row" class="whitespace-nowrap px-4 py-4 font-mono font-medium">
                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                class="rounded text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                {{ $task->reference }}
                            </a>
                        </th>

                        <td class="min-w-48 max-w-sm break-words px-4 py-4">
                            {{ $task->title }}
                        </td>

                        <td class="whitespace-nowrap px-4 py-4">
                            <x-tasks.badge :value="$task->status" />
                        </td>

                        <td class="px-4 py-4">
                            <x-tasks.badge :value="$task->priority" />
                        </td>

                        <td class="px-4 py-4">
                            {{ $task->assignee?->name ?? 'Unassigned' }}

                            @if ($task->assignee && !$task->assignee->is_active)
                                <span class="block text-xs text-slate-500">Inactive</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-4 py-4 text-slate-600">
                            {{ $task->due_date?->format('M j, Y') ?? 'No due date' }}
                        </td>

                        <td class="px-4 py-4">
                            <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                                aria-label="Edit {{ $task->reference }}"
                                class="rounded font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                            No tasks found. Add a task or adjust your filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $tasks->links() }}
    </div>
</x-layouts.app>
