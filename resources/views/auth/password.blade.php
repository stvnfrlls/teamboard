<x-layouts.app title="Change password">
    <section aria-labelledby="password-heading" class="ui-card max-w-2xl p-6 sm:p-8">
        <div class="border-b border-slate-100 pb-6">
            <h2 id="password-heading" class="text-lg font-semibold text-slate-900">
                Choose a new password
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Use at least 12 characters. After saving, you’ll need to sign in again
                on your devices.
            </p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')

            <x-form.input name="current_password" label="Current password" type="password"
                autocomplete="current-password" required autofocus />

            <x-form.input name="password" label="New password" type="password" autocomplete="new-password"
                minlength="12" required />

            <x-form.input name="password_confirmation" label="Confirm new password" type="password"
                autocomplete="new-password" minlength="12" required />

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
                <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto">
                    Change password and sign out
                </button>

                <a href="{{ route('dashboard') }}" class="ui-button ui-button-secondary w-full sm:w-auto">
                    Cancel
                </a>
            </div>
        </form>
    </section>
</x-layouts.app>
