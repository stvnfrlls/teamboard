<x-layouts.app title="Dashboard">
    <p class="mb-8 text-slate-600">
        Welcome back, {{ auth()->user()->name }}.
    </p>

    <section aria-labelledby="projects-heading" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 id="projects-heading" class="text-lg font-semibold">Projects</h2>

        <p class="mt-2 text-sm text-slate-600">
            Browse your projects, view their details, and see the people involved.
        </p>

        <a href="{{ route('projects.index') }}"
            class="mt-5 inline-block rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            View projects
        </a>
    </section>
</x-layouts.app>
