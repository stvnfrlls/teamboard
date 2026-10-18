@php
    $editing = $user->exists;
    $ownAccount = $editing && $user->is(auth()->user());
    $roleOptions = [];

    foreach ($roles as $role) {
        $roleOptions[$role->value] = $role->label();
    }
@endphp

<x-layouts.app :title="$editing ? 'Edit user' : 'Add user'">
    <a href="{{ route('users.index') }}" class="ui-button ui-button-secondary mb-6">
        <span aria-hidden="true">←</span>
        Back to users
    </a>

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
        class="ui-card max-w-4xl space-y-7 p-6 sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <fieldset>
            <legend class="mb-5 text-base font-semibold text-slate-900">
                Profile details
            </legend>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Full name" :value="$user->name" autocomplete="name" maxlength="255"
                    required />

                <x-form.input name="email" label="Email address" type="email" :value="$user->email" autocomplete="email"
                    maxlength="255" required />
            </div>
        </fieldset>

        <fieldset class="border-t border-slate-100 pt-6">
            <legend class="pr-3 text-base font-semibold text-slate-900">
                Account access
            </legend>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="role" label="Role" :options="$roleOptions" :value="$user->role->value" :disabled="$ownAccount"
                    required />

                <x-form.select name="is_active" label="Account status" :options="[1 => 'Active', 0 => 'Inactive']" :value="(int) $user->is_active"
                    :disabled="$ownAccount" required />
            </div>

            @if ($ownAccount)
                <input type="hidden" name="role" value="{{ $user->role->value }}">
                <input type="hidden" name="is_active" value="1">

                <p
                    class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm leading-6 text-indigo-800">
                    Your own account must remain an active admin.
                </p>
            @endif
        </fieldset>

        @unless ($editing)
            <fieldset class="border-t border-slate-100 pt-6">
                <legend class="pr-3 text-base font-semibold text-slate-900">
                    Initial password
                </legend>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="password" label="Password — at least 12 characters" type="password"
                        autocomplete="new-password" minlength="12" required />

                    <x-form.input name="password_confirmation" label="Confirm password" type="password"
                        autocomplete="new-password" minlength="12" required />
                </div>
            </fieldset>
        @endunless

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-6">
            <button type="submit" class="ui-button ui-button-primary">
                {{ $editing ? 'Save changes' : 'Create user' }}
            </button>

            <a href="{{ route('users.index') }}" class="ui-button ui-button-secondary">
                Cancel
            </a>
        </div>
    </form>
</x-layouts.app>
