<x-layouts.app title="My Tasks">
    <section aria-labelledby="assignments-heading" class="ui-card mb-6 p-5 sm:p-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="assignments-heading" class="text-lg font-semibold text-slate-900">
                    Your assignments
                </h2>

                <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                    Tasks assigned to you across projects you can access, ordered by due date.
                </p>
            </div>

            <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">
                {{ $tasks->total() }} matching tasks
            </span>
        </div>

        <form method="GET" action="{{ route('tasks.mine') }}"
            class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-form.input name="search" label="Search tasks" type="search" :value="$filters['search'] ?? ''" maxlength="100"
                placeholder="Title or task reference" />

            <x-form.select name="status" label="Status" :options="['' => 'All statuses'] + $statuses" :value="$filters['status'] ?? ''" />

            <x-form.select name="priority" label="Priority" :options="['' => 'All priorities'] + $priorities" :value="$filters['priority'] ?? ''" />

            <x-form.select name="due" label="Deadline" :options="['' => 'Any date', 'overdue' => 'Overdue', 'today' => 'Due today']" :value="$filters['due'] ?? ''" />

            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-4">
                <button type="submit" class="ui-button ui-button-primary">
                    Apply filters
                </button>

                <a href="{{ route('tasks.mine') }}" class="ui-button ui-button-secondary">
                    Clear
                </a>
            </div>
        </form>

        <p class="mt-5 border-t border-slate-100 pt-4 text-xs leading-5 text-slate-500">
            Today and overdue use the UTC calendar. Completed tasks are excluded from deadline filters.
        </p>
    </section>

    @include('tasks.list', [
        'showProject' => true,
        'caption' => 'My assigned tasks',
    ])
</x-layouts.app>
