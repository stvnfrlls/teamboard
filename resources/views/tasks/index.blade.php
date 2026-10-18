<x-layouts.app :title="$project->name . ' · Tasks'">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('projects.show', $project) }}" class="ui-button ui-button-secondary">
            <span aria-hidden="true">←</span>
            Back to project
        </a>

        <a href="{{ route('projects.tasks.create', $project) }}" class="ui-button ui-button-primary">
            <span aria-hidden="true">+</span>
            Add task
        </a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section aria-labelledby="task-workspace-heading" class="ui-card mb-6 p-5 sm:p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 id="task-workspace-heading" class="text-lg font-semibold text-slate-900">
                    Project tasks
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Find tasks or switch views to follow their progress.
                </p>
            </div>

            <nav aria-label="Task views" class="flex gap-1 rounded-2xl bg-slate-100 p-1">
                @foreach (['list' => 'List', 'board' => 'Board'] as $mode => $label)
                    <a href="{{ route('projects.tasks.index', array_merge($filters, ['project' => $project, 'view' => $mode])) }}"
                        @if ($viewMode === $mode) aria-current="page" @endif @class([
                            'ui-button min-w-20',
                            'bg-white text-indigo-700 shadow-sm' => $viewMode === $mode,
                            'text-slate-600 hover:bg-white/70 hover:text-slate-900' =>
                                $viewMode !== $mode,
                        ])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>

        <form method="GET" action="{{ route('projects.tasks.index', $project) }}"
            class="grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 xl:grid-cols-4">
            <input type="hidden" name="view" value="{{ $viewMode }}">

            <x-form.input name="search" label="Search tasks" type="search" :value="$filters['search'] ?? ''" maxlength="100"
                placeholder="Title or task reference" />

            <x-form.select name="status" label="Status" :options="['' => 'All statuses'] + $statuses" :value="$filters['status'] ?? ''" />

            <x-form.select name="priority" label="Priority" :options="['' => 'All priorities'] + $priorities" :value="$filters['priority'] ?? ''" />

            <div class="flex flex-wrap items-end gap-3">
                <button type="submit" class="ui-button ui-button-primary">
                    Apply filters
                </button>

                <a href="{{ route('projects.tasks.index', ['project' => $project, 'view' => $viewMode]) }}"
                    class="ui-button ui-button-secondary">
                    Clear
                </a>
            </div>
        </form>
    </section>

    @if ($viewMode === 'board')
        @include('tasks.board')
    @else
        @include('tasks.list')
    @endif
</x-layouts.app>
