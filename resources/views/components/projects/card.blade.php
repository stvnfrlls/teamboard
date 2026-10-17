@props(['project'])

<article class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-3 flex items-start justify-between gap-4">
        <span class="rounded-md bg-indigo-50 px-2.5 py-1 font-mono text-xs font-semibold text-indigo-700">
            {{ $project->key }}
        </span>

        @can('update', $project)
            <a href="{{ route('projects.edit', $project) }}" aria-label="Edit {{ $project->name }}"
                class="rounded text-sm text-slate-500 hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Edit
            </a>
        @endcan
    </div>

    <h2 class="text-lg font-semibold">
        <a href="{{ route('projects.show', $project) }}"
            class="rounded break-words hover:text-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            {{ $project->name }}
        </a>
    </h2>

    <p class="mt-2 line-clamp-3 break-words text-sm leading-6 text-slate-600">
        {{ $project->description ?: 'No description provided.' }}
    </p>

    <p class="mt-auto pt-6 text-xs text-slate-500">
        {{ $project->members_count }}
        {{ \Illuminate\Support\Str::plural('member', $project->members_count) }}
    </p>
</article>
