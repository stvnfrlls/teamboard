<x-layouts.app title="Dashboard">
    <p class="mb-8 text-slate-600">
        Welcome back, {{ auth()->user()->name }}.
    </p>

    <section aria-labelledby="projects-heading"
        class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
        <h2 id="projects-heading" class="text-lg font-semibold">
            No projects yet
        </h2>

        <p class="mt-2 text-sm text-slate-600">
            Your projects and assigned tasks will appear here.
        </p>
    </section>
</x-layouts.app>
