@props(['title' => 'Dashboard'])

<x-layouts.base :title="$title">
    <div data-app-shell data-sidebar-collapsed="false" class="ui-shell min-w-0">
        <aside id="workspace-sidebar" aria-label="Workspace sidebar"
            class="ui-sidebar fixed inset-y-0 left-0 z-40 w-64 flex-col overflow-y-auto border-r border-slate-200 bg-white px-5 py-6">
            <a href="{{ route('dashboard') }}"
                class="mb-9 flex items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500">
                <span aria-hidden="true"
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-600 text-sm font-bold text-white">
                    TB
                </span>

                <span class="min-w-0">
                    <span class="block truncate text-lg font-bold tracking-tight text-slate-900">
                        {{ config('app.name') }}
                    </span>
                    <span class="block text-xs text-slate-500">Project workspace</span>
                </span>
            </a>

            <x-layouts.navigation :unread-notification-count="$unreadNotificationCount" label="Desktop navigation" />
        </aside>

        <div class="ui-shell-content min-h-screen min-w-0">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                    <a href="{{ route('dashboard') }}"
                        class="min-w-0 truncate rounded-lg text-lg font-bold tracking-tight text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 lg:hidden">
                        {{ config('app.name') }}
                    </a>

                    <p class="hidden min-w-0 flex-1 truncate text-sm text-slate-500 lg:block">
                        Workspace
                        <span aria-hidden="true" class="mx-2 text-slate-300">/</span>
                        <span class="font-medium text-slate-700">{{ $title }}</span>
                    </p>

                    <button type="button" data-sidebar-toggle hidden aria-controls="workspace-sidebar"
                        aria-expanded="true" aria-label="Hide sidebar"
                        class="ui-button ui-button-secondary hidden shrink-0 lg:inline-flex">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" class="size-4">
                            <rect x="3" y="4" width="18" height="16" rx="2" />
                            <path d="M9 4v16" />
                        </svg>

                        <span data-sidebar-toggle-label>Hide sidebar</span>
                    </button>

                    <details class="ui-menu relative ml-auto shrink-0 lg:hidden">
                        <summary class="ui-button ui-button-secondary">
                            Menu
                        </summary>

                        <div
                            class="absolute top-full right-0 mt-3 max-h-[calc(100dvh-6rem)] w-72 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
                            <x-layouts.navigation :unread-notification-count="$unreadNotificationCount" label="Mobile navigation" />
                        </div>
                    </details>
                </div>
            </header>

            <main id="main-content" class="mx-auto min-w-0 max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
                <h1 class="mb-6 break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                    {{ $title }}
                </h1>

                <x-flash-status class="mb-6" />

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
