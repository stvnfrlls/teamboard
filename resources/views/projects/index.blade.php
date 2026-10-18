<x-layouts.app title="Projects">
    <section aria-labelledby="projects-heading" class="ui-card mb-6 p-5 sm:p-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="projects-heading" class="text-lg font-semibold text-slate-900">
                    Your projects
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Browse the projects you can access and keep their work moving.
                </p>
            </div>

            @can('create', \App\Models\Project::class)
                <a href="{{ route('projects.create') }}" class="ui-button ui-button-primary">
                    <span aria-hidden="true">+</span>
                    Add project
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('projects.index') }}"
            class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-5">
            <div class="min-w-0 flex-1 sm:max-w-md">
                <x-form.input name="search" label="Search projects" type="search" :value="$search" maxlength="100"
                    placeholder="Project name or key" />
            </div>

            <button type="submit" class="ui-button ui-button-primary">
                Search
            </button>

            @if ($search !== '')
                <a href="{{ route('projects.index') }}" class="ui-button ui-button-secondary">
                    Clear
                </a>
            @endif
        </form>
    </section>

    <p class="mb-4 text-sm text-slate-500">
        {{ $projects->total() }} matching projects
    </p>

    @if ($projects->isEmpty())
        <section class="ui-card px-6 py-16 text-center">
            <h2 class="text-lg font-semibold text-slate-900">No projects found</h2>

            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
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
