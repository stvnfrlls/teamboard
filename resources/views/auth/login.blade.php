<x-layouts.base title="Sign in">
    <main id="main-content" class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center justify-center gap-3">
                <span aria-hidden="true"
                    class="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-600 text-sm font-bold text-white">
                    TB
                </span>

                <div class="min-w-0">
                    <p class="break-words text-xl font-bold tracking-tight text-slate-900">
                        {{ config('app.name') }}
                    </p>
                    <p class="text-xs text-slate-500">Project workspace</p>
                </div>
            </div>

            <section aria-labelledby="signin-heading" class="ui-card p-6 sm:p-8">
                <h1 id="signin-heading" class="text-2xl font-bold tracking-tight text-slate-900">
                    Sign in to your workspace
                </h1>

                <p class="mt-3 text-sm leading-6 text-slate-500">
                    Keep your projects, tasks, and team in one place.
                </p>

                <div class="mt-6 border-t border-slate-100 pt-6">
                    <x-flash-status class="mb-5" />

                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                        @csrf

                        <x-form.input name="email" label="Email address" type="email" autocomplete="username"
                            maxlength="255" required autofocus />

                        <x-form.input name="password" label="Password" type="password" autocomplete="current-password"
                            required />

                        <label class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-slate-600">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                                class="size-4 shrink-0 accent-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                            Keep me signed in
                        </label>

                        <button type="submit" class="ui-button ui-button-primary w-full">
                            Sign in
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</x-layouts.base>
