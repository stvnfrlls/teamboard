<x-layouts.app title="Projects">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('projects.index') }}" class="flex flex-wrap items-end gap-3">
            <x-form.input name="search" label="Search projects" type="search" :value="$search" maxlength="100"
                placeholder="Project name or key" />

            <button type="submit"
                class="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Search
            </button>

            @if ($search !== '')
                <a href="{{ route('projects.index') }}" class="rounded px-2 py-2.5 text-sm text-indigo-700 underline">
                    Clear
                </a>
            @endif
        </form>

        @can('create', \App\Models\Project::class)
            <a href="{{ route('projects.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Add project
            </a>
        @endcan
    </div>

    @if ($projects->isEmpty())
        <section class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
            <h2 class="text-lg font-semibold">No projects found</h2>

            <p class="mt-2 text-sm text-slate-600">
                {{ $search !== '' ? 'Try another project name or key.' : 'Projects you can access will appear here.' }}
            </p>
        </section>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                <x-projects.card :project="$project" />
            @endforeach
        </div>
    @endif

    <div class="mt-6">
        {{ $projects->links() }}
    </div>
</x-layouts.app>
