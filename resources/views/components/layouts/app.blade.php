@props(['title' => 'Dashboard'])

<x-layouts.base :title="$title">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}"
                class="rounded text-xl font-bold tracking-tight text-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-600">
                {{ config('app.name') }}
            </a>

            <div class="flex min-w-0 flex-wrap items-center gap-4">
                @can('viewAny', \App\Models\Project::class)
                    <a href="{{ route('projects.index') }}" @if (request()->routeIs('projects.*')) aria-current="page" @endif
                        class="rounded text-sm font-medium text-indigo-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Projects
                    </a>
                @endcan
                @can('viewAny', \App\Models\User::class)
                    <a href="{{ route('users.index') }}" @if (request()->routeIs('users.*')) aria-current="page" @endif
                        class="rounded text-sm font-medium text-indigo-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Users
                    </a>
                @endcan
                <span class="max-w-40 truncate text-sm text-slate-600 sm:max-w-64">
                    {{ auth()->user()->name }}
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button type="submit"
                        class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main id="main-content" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        @if (session('status'))
            <div role="status"
                class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</x-layouts.base>
