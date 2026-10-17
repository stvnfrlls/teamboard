@php
    $editing = $task->exists;
    $version = old('lock_version', $task->lock_version);
    $version = is_scalar($version) ? (string) $version : '';
@endphp

<x-layouts.app :title="$editing ? 'Edit ' . $task->reference : 'Add task'">
    <a href="{{ route('projects.tasks.index', $project) }}"
        class="mb-6 inline-block rounded text-sm text-indigo-700 underline">
        Back to tasks
    </a>

    <form method="POST"
        action="{{ $editing ? route('projects.tasks.update', [$project, $task]) : route('projects.tasks.store', $project) }}"
        class="max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $version }}">
        @endif

        @error('lock_version')
            <div role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p>{{ $message }}</p>

                @if ($editing)
                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                        class="mt-2 inline-block rounded font-semibold underline">
                        Reload latest task
                    </a>
                @endif
            </div>
        @enderror

        <p class="text-sm text-slate-500">
            Project: {{ $project->name }} ({{ $project->key }})
        </p>

        <x-form.input name="title" label="Task title" :value="$task->title" maxlength="255" required autofocus />

        <x-form.textarea name="description" label="Description (optional)" :value="$task->description" maxlength="10000" />

        <div class="grid gap-6 sm:grid-cols-2">
            <x-form.select name="status" label="Status" :options="$statuses" :value="$task->status->value" required />

            <x-form.select name="priority" label="Priority" :options="$priorities" :value="$task->priority->value" required />

            <x-form.select name="assignee_id" label="Assignee" :options="$assigneeOptions" :value="$task->assignee_id ?? ''" />

            <x-form.input name="due_date" label="Due date (optional)" type="date" :value="$task->due_date?->format('Y-m-d')" />
        </div>

        <p class="text-sm text-slate-500">
            Assignees must be active project members. Choose Unassigned if no owner is needed.
        </p>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit"
                class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                {{ $editing ? 'Save changes' : 'Create task' }}
            </button>

            <a href="{{ route('projects.tasks.index', $project) }}" class="rounded text-sm text-slate-600 underline">
                Cancel
            </a>
        </div>
    </form>
</x-layouts.app>
