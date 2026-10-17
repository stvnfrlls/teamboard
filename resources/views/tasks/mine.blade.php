<x-layouts.app title="My Tasks">
    <p class="mb-2 text-slate-600">
        Tasks assigned to you across projects you can access, ordered by due date.
    </p>

    <p class="mb-6 text-sm text-slate-500">
        Today and overdue use the UTC calendar. Completed tasks are excluded from deadline filters.
    </p>

    <form method="GET" action="{{ route('tasks.mine') }}"
        class="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-form.input name="search" label="Search tasks" type="search" :value="$filters['search'] ?? ''" maxlength="100"
            placeholder="Title or task reference" />

        <x-form.select name="status" label="Status" :options="['' => 'All statuses'] + $statuses" :value="$filters['status'] ?? ''" />

        <x-form.select name="priority" label="Priority" :options="['' => 'All priorities'] + $priorities" :value="$filters['priority'] ?? ''" />

        <x-form.select name="due" label="Deadline" :options="['' => 'Any date', 'overdue' => 'Overdue', 'today' => 'Due today']" :value="$filters['due'] ?? ''" />

        <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-4">
            <button type="submit"
                class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Apply filters
            </button>

            <a href="{{ route('tasks.mine') }}" class="rounded text-sm text-indigo-700 underline">
                Clear
            </a>
        </div>
    </form>

    <p class="mb-4 text-sm text-slate-600">
        {{ $tasks->total() }} matching tasks
    </p>

    @include('tasks.list', [
        'showProject' => true,
        'caption' => 'My assigned tasks',
    ])
</x-layouts.app>
