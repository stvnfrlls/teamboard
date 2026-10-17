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
