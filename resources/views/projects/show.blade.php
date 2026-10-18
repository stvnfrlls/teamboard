<x-layouts.app :title="$project->name">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('projects.index') }}" class="ui-button ui-button-secondary">
            <span aria-hidden="true">←</span>
            Back to projects
        </a>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('projects.tasks.index', $project) }}" class="ui-button ui-button-primary">
                View tasks
            </a>

            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="ui-button ui-button-secondary">
                    Edit project
                </a>
            @endcan
        </div>
    </div>

    <x-tasks.summary :summary="$taskSummary" task-route="projects.tasks.index" :route-parameters="['project' => $project]" class="mb-6" />

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <section aria-labelledby="details-heading" class="ui-card min-w-0 p-6 sm:p-8 xl:col-span-2">
            <span class="rounded-lg bg-indigo-50 px-3 py-1.5 font-mono text-xs font-semibold text-indigo-700">
                {{ $project->key }}
            </span>

            <h2 id="details-heading" class="mt-6 text-lg font-semibold text-slate-900">
                About this project
            </h2>

            <p class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">
                {{ $project->description ?: 'No description provided.' }}</p>

            <dl class="mt-8 border-t border-slate-100 pt-5 text-sm">
                <dt class="text-xs font-medium text-slate-500">Created by</dt>
                <dd class="mt-2 break-words font-medium text-slate-800">
                    {{ $project->creator->name }}
                </dd>
            </dl>
        </section>

        <section aria-labelledby="members-heading" class="ui-card min-w-0 p-6">
            <h2 id="members-heading" class="text-base font-semibold text-slate-900">
                Project members ({{ $project->members->count() }})
            </h2>

            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($project->members->sortBy('name') as $member)
                    <li class="py-4">
                        <p class="break-words text-sm font-semibold text-slate-800">
                            {{ $member->name }}

                            @unless ($member->is_active)
                                <span class="ml-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-normal text-slate-500">
                                    Inactive
                                </span>
                            @endunless
                        </p>

                        <p class="mt-2 break-all text-xs leading-5 text-slate-500">
                            {{ $member->email }}
                        </p>
                    </li>
                @empty
                    <li class="py-6 text-sm text-slate-500">
                        No members assigned.
                    </li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
