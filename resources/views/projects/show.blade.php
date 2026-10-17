<x-layouts.app :title="$project->name">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('projects.index') }}" class="rounded text-sm text-indigo-700 underline">
            Back to projects
        </a>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('projects.tasks.index', $project) }}"
                class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                View tasks
            </a>

            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Edit project
                </a>
            @endcan
        </div>
    </div>

    <x-tasks.summary :summary="$taskSummary" task-route="projects.tasks.index" :route-parameters="['project' => $project]" class="mb-6" />


    <div class="grid gap-6 lg:grid-cols-3">
        <section aria-labelledby="details-heading"
            class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <span class="rounded-md bg-indigo-50 px-2.5 py-1 font-mono text-xs font-semibold text-indigo-700">
                {{ $project->key }}
            </span>

            <h2 id="details-heading" class="mt-5 text-lg font-semibold">About this project</h2>

            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">
                {{ $project->description ?: 'No description provided.' }}</p>

            <dl class="mt-8 border-t border-slate-100 pt-5 text-sm">
                <dt class="text-slate-500">Created by</dt>
                <dd class="mt-1 font-medium">{{ $project->creator->name }}</dd>
            </dl>
        </section>

        <section aria-labelledby="members-heading" class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 id="members-heading" class="text-lg font-semibold">
                Project members ({{ $project->members->count() }})
            </h2>

            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($project->members->sortBy('name') as $member)
                    <li class="py-3">
                        <p class="break-words text-sm font-medium">
                            {{ $member->name }}

                            @unless ($member->is_active)
                                <span class="ml-1 text-xs font-normal text-slate-500">Inactive</span>
                            @endunless
                        </p>

                        <p class="mt-1 break-all text-xs text-slate-500">
                            {{ $member->email }}
                        </p>
                    </li>
                @empty
                    <li class="py-3 text-sm text-slate-500">
                        No members assigned.
                    </li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
