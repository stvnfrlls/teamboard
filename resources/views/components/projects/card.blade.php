@props(['project'])

<article class="ui-card flex h-full min-w-0 flex-col p-6 transition-colors hover:border-indigo-300">
    <div class="mb-5 flex items-center justify-between gap-3">
        <span class="rounded-lg bg-indigo-50 px-3 py-1.5 font-mono text-xs font-semibold text-indigo-700">
            {{ $project->key }}
        </span>

        @can('update', $project)
            <a href="{{ route('projects.edit', $project) }}" aria-label="Edit {{ $project->name }}"
                class="ui-button ui-button-secondary min-h-9 px-3 py-1.5 text-xs">
                Edit
            </a>
        @endcan
    </div>

    <h2 class="break-words text-lg leading-7 font-semibold">
        <a href="{{ route('projects.show', $project) }}" class="ui-link">
            {{ $project->name }}
        </a>
    </h2>

    <p class="mt-3 line-clamp-3 break-words text-sm leading-6 text-slate-500">
        {{ $project->description ?: 'No description provided.' }}
    </p>

    <div class="mt-auto pt-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <p class="text-xs text-slate-500">
                {{ $project->members_count }}
                {{ \Illuminate\Support\Str::plural('member', $project->members_count) }}
            </p>

            <a href="{{ route('projects.show', $project) }}" aria-label="Open {{ $project->name }}"
                class="ui-link text-xs">
                Open project <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</article>
