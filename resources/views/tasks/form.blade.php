@php
    $editing = $task->exists;
    $version = old('lock_version', $task->lock_version);
    $version = is_scalar($version) ? (string) $version : '';
@endphp

<x-layouts.app :title="$editing ? 'Edit ' . $task->reference : 'Add task'">
    <a href="{{ route('projects.tasks.index', $project) }}" class="ui-button ui-button-secondary mb-6">
        <span aria-hidden="true">←</span>
        Back to tasks
    </a>

    <form method="POST"
        action="{{ $editing ? route('projects.tasks.update', [$project, $task]) : route('projects.tasks.store', $project) }}"
        class="ui-card max-w-4xl space-y-7 p-6 sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $version }}">
        @endif

        @error('lock_version')
            <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p>{{ $message }}</p>

                @if ($editing)
                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                        class="ui-button ui-button-secondary mt-3">
                        Reload latest task
                    </a>
                @endif
            </div>
        @enderror

        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 pb-6">
            <span class="rounded-lg bg-indigo-50 px-3 py-1.5 font-mono text-xs font-semibold text-indigo-700">
                {{ $project->key }}
            </span>

            <p class="min-w-0 break-words text-sm font-medium text-slate-600">
                {{ $project->name }}
            </p>
        </div>

        <fieldset class="space-y-5">
            <legend class="mb-4 text-base font-semibold text-slate-900">
                Task information
            </legend>

            <x-form.input name="title" label="Task title" :value="$task->title" maxlength="255" required autofocus />

            <x-form.textarea name="description" label="Description (optional)" :value="$task->description" maxlength="10000" />
        </fieldset>

        <fieldset class="border-t border-slate-100 pt-6">
            <legend class="pr-3 text-base font-semibold text-slate-900">
                Workflow and ownership
            </legend>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="status" label="Status" :options="$statuses" :value="$task->status->value" required />

                <x-form.select name="priority" label="Priority" :options="$priorities" :value="$task->priority->value" required />

                <x-form.select name="assignee_id" label="Assignee" :options="$assigneeOptions" :value="$task->assignee_id ?? ''" />

                <x-form.input name="due_date" label="Due date (optional)" type="date" :value="$task->due_date?->format('Y-m-d')" />
            </div>

            <p class="mt-4 text-xs leading-6 text-slate-500">
                Assignees must be active project members. Choose Unassigned if no owner is needed.
            </p>
        </fieldset>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="ui-button ui-button-primary">
                {{ $editing ? 'Save changes' : 'Create task' }}
            </button>

            <a href="{{ route('projects.tasks.index', $project) }}" class="ui-button ui-button-secondary">
                Cancel
            </a>
        </div>
    </form>
</x-layouts.app>
