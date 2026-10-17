@php
    $editing = $project->exists;
@endphp

<x-layouts.app :title="$editing ? 'Edit project' : 'Add project'">
    <a href="{{ route('projects.index') }}" class="mb-6 inline-block rounded text-sm text-indigo-700 underline">
        Back to projects
    </a>

    <form method="POST" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}"
        class="max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <x-form.input name="name" label="Project name" :value="$project->name" maxlength="255" required />

        @if ($editing)
            <div>
                <p class="text-sm font-medium text-slate-700">Project key</p>
                <p class="mt-2 font-mono text-sm font-semibold">{{ $project->key }}</p>
                <p class="mt-2 text-sm text-slate-500">
                    Project keys stay fixed after creation.
                </p>

                @error('key')
                    <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div>
                <x-form.input name="key" label="Project key" minlength="2" maxlength="10"
                    pattern="[A-Za-z][A-Za-z0-9]{1,9}" autocomplete="off" spellcheck="false" aria-describedby="key-help"
                    class="font-mono uppercase" required />

                <p id="key-help" class="mt-2 text-sm text-slate-500">
                    Use 2–10 letters or numbers, starting with a letter. Example: TMMS.
                </p>
            </div>
        @endif

        <x-form.textarea name="description" label="Description (optional)" :value="$project->description" maxlength="10000" />

        <x-form.members :members="$availableMembers" :selected="$selectedMemberIds" />

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit"
                class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                {{ $editing ? 'Save changes' : 'Create project' }}
            </button>

            <a href="{{ route('projects.index') }}" class="rounded text-sm text-slate-600 underline">
                Cancel
            </a>
        </div>
    </form>
</x-layouts.app>
