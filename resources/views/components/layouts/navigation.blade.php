@props(['unreadNotificationCount' => 0, 'label' => 'Main navigation'])

@php
    $user = auth('web')->user();
    $initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1));
@endphp

<div class="flex min-h-0 flex-1 flex-col">
    <p class="mb-3 px-3 text-xs font-semibold tracking-widest text-slate-400 uppercase">
        Workspace
    </p>

    <nav aria-label="{{ $label }}" class="space-y-1">
        <a href="{{ route('dashboard') }}" class="ui-nav-link"
            @if (request()->routeIs('dashboard')) aria-current="page" @endif>
            Dashboard
        </a>

        <a href="{{ route('tasks.mine') }}" class="ui-nav-link"
            @if (request()->routeIs('tasks.mine')) aria-current="page" @endif>
            My Tasks
        </a>

        @can('viewAny', \App\Models\Project::class)
            <a href="{{ route('projects.index') }}" class="ui-nav-link"
                @if (request()->routeIs('projects.*')) aria-current="page" @endif>
                Projects
            </a>
        @endcan

        <a href="{{ route('notifications.index') }}" class="ui-nav-link"
            aria-label="Notifications, {{ $unreadNotificationCount }} unread"
            @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
            <span>Notifications</span>

            @if ($unreadNotificationCount > 0)
                <span aria-hidden="true"
                    class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">
                    {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                </span>
            @endif
        </a>

        @can('viewAny', \App\Models\User::class)
            <a href="{{ route('users.index') }}" class="ui-nav-link"
                @if (request()->routeIs('users.*')) aria-current="page" @endif>
                Users
            </a>
        @endcan
    </nav>

    <div class="mt-auto pt-8">
        <div class="border-t border-slate-200 pt-5">
            <div class="mb-4 flex min-w-0 items-center gap-3 px-2">
                <span aria-hidden="true"
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-50 font-semibold text-indigo-700">
                    {{ $initial }}
                </span>

                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">
                        {{ $user->name }}
                    </p>
                    <p class="text-xs text-slate-500">Your account</p>
                </div>
            </div>

            <a href="{{ route('password.edit') }}" class="ui-nav-link"
                @if (request()->routeIs('password.*')) aria-current="page" @endif>
                Change password
            </a>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf

                <button type="submit" class="ui-button ui-button-secondary w-full">
                    Sign out
                </button>
            </form>
        </div>
    </div>
</div>
