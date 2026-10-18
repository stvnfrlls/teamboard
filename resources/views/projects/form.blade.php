@php
    $editing = $project->exists;
@endphp

<x-layouts.app :title="$editing ? 'Edit project' : 'Add project'">
    <a href="{{ route('projects.index') }}" class="ui-button ui-button-secondary mb-6">
        <span aria-hidden="true">←</span>
        Back to projects
    </a>

    <form method="POST" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}"
        class="ui-card max-w-4xl space-y-7 p-6 sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="space-y-5">
            <legend class="mb-4 text-base font-semibold text-slate-900">
                Project details
            </legend>

            <x-form.input name="name" label="Project name" :value="$project->name" maxlength="255" required />

            @if ($editing)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-700">Project key</p>

                    <p class="mt-2 font-mono text-sm font-semibold text-indigo-700">
                        {{ $project->key }}
                    </p>

                    <p class="mt-2 text-xs leading-6 text-slate-500">
                        Project keys stay fixed after creation.
                    </p>

                    @error('key')
                        <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div>
                    <x-form.input name="key" label="Project key" minlength="2" maxlength="10"
                        pattern="[A-Za-z][A-Za-z0-9]{1,9}" autocomplete="off" spellcheck="false"
                        aria-describedby="key-help" class="font-mono uppercase" required />

                    <p id="key-help" class="mt-2 text-xs leading-6 text-slate-500">
                        Use 2–10 letters or numbers, starting with a letter. Example: TMMS.
                    </p>
                </div>
            @endif

            <x-form.textarea name="description" label="Description (optional)" :value="$project->description" maxlength="10000" />
        </fieldset>

        <div class="border-t border-slate-100 pt-6">
            <x-form.members :members="$availableMembers" :selected="$selectedMemberIds" />
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="ui-button ui-button-primary">
                {{ $editing ? 'Save changes' : 'Create project' }}
            </button>

            <a href="{{ route('projects.index') }}" class="ui-button ui-button-secondary">
                Cancel
            </a>
        </div>
    </form>
</x-layouts.app>
