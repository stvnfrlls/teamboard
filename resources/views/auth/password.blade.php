<x-layouts.app title="Change password">
    <section aria-labelledby="password-heading"
        class="max-w-xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 id="password-heading" class="text-lg font-semibold">
            Choose a new password
        </h2>

        <p class="mt-2 text-sm leading-6 text-slate-600">
            Use at least 12 characters. After saving, you’ll need to sign in again
            on your devices.
        </p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')

            <x-form.input name="current_password" label="Current password" type="password"
                autocomplete="current-password" required autofocus />

            <x-form.input name="password" label="New password" type="password" autocomplete="new-password"
                minlength="12" required />

            <x-form.input name="password_confirmation" label="Confirm new password" type="password"
                autocomplete="new-password" minlength="12" required />

            <div class="flex flex-wrap items-center gap-4">
                <button type="submit"
                    class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Change password and sign out
                </button>

                <a href="{{ route('dashboard') }}"
                    class="rounded text-sm font-medium text-slate-600 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</x-layouts.app>
