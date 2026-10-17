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

    <nav aria-label="Task views" class="mb-6 flex gap-2">
        @foreach (['list' => 'List', 'board' => 'Board'] as $mode => $label)
            <a href="{{ route('projects.tasks.index', array_merge($filters, ['project' => $project, 'view' => $mode])) }}"
                @if ($viewMode === $mode) aria-current="page" @endif @class([
                    'rounded-lg border px-4 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                    'border-indigo-600 bg-indigo-600 text-white' => $viewMode === $mode,
                    'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' =>
                        $viewMode !== $mode,
                ])>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($errors->any())
        <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('projects.tasks.index', $project) }}"
        class="mb-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input type="hidden" name="view" value="{{ $viewMode }}">

        <x-form.input name="search" label="Search tasks" type="search" :value="$filters['search'] ?? ''" maxlength="100"
            placeholder="Title or task reference" />

        <x-form.select name="status" label="Status" :options="['' => 'All statuses'] + $statuses" :value="$filters['status'] ?? ''" />

        <x-form.select name="priority" label="Priority" :options="['' => 'All priorities'] + $priorities" :value="$filters['priority'] ?? ''" />

        <div class="flex items-end gap-4">
            <button type="submit"
                class="cursor-pointer rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Apply filters
            </button>

            <a href="{{ route('projects.tasks.index', ['project' => $project, 'view' => $viewMode]) }}"
                class="rounded py-2.5 text-sm text-indigo-700 underline">
                Clear
            </a>
        </div>
    </form>

    @if ($viewMode === 'board')
        @include('tasks.board')
    @else
        @include('tasks.list')
    @endif
</x-layouts.app>
