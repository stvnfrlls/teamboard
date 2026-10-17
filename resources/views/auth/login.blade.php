<x-layouts.base title="Sign in">
    <main id="main-content" class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <p class="text-2xl font-bold tracking-tight text-indigo-700">
                    {{ config('app.name') }}
                </p>

                <h1 class="mt-6 text-2xl font-semibold tracking-tight">
                    Sign in to your workspace
                </h1>

                <p class="mt-2 text-sm text-slate-600">
                    Keep your projects, tasks, and team in one place.
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <x-flash-status class="mb-5" />
                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

                    <x-form.input name="email" label="Email address" type="email" autocomplete="username"
                        maxlength="255" required autofocus />

                    <x-form.input name="password" label="Password" type="password" autocomplete="current-password"
                        required />

                    <label class="flex w-fit cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                            class="size-4 accent-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Keep me signed in
                    </label>

                    <button type="submit"
                        class="w-full cursor-pointer rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Sign in
                    </button>
                </form>
            </div>
        </div>
    </main>
</x-layouts.base>
