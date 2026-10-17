@php
    $editing = $user->exists;
    $ownAccount = $editing && $user->is(auth()->user());
    $roleOptions = [];

    foreach ($roles as $role) {
        $roleOptions[$role->value] = $role->label();
    }
@endphp

<x-layouts.app :title="$editing ? 'Edit user' : 'Add user'">
    <a href="{{ route('users.index') }}" class="mb-6 inline-block rounded text-sm text-indigo-700 underline">
        Back to users
    </a>

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
        class="max-w-2xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @csrf

        @if ($editing)
            @method('PUT')
        @endif

        <x-form.input name="name" label="Full name" :value="$user->name" autocomplete="name" maxlength="255" required />

        <x-form.input name="email" label="Email address" type="email" :value="$user->email" autocomplete="email"
            maxlength="255" required />

        <x-form.select name="role" label="Role" :options="$roleOptions" :value="$user->role->value" :disabled="$ownAccount" required />

        <x-form.select name="is_active" label="Account status" :options="[1 => 'Active', 0 => 'Inactive']" :value="(int) $user->is_active" :disabled="$ownAccount"
            required />

        @if ($ownAccount)
            <input type="hidden" name="role" value="{{ $user->role->value }}">
            <input type="hidden" name="is_active" value="1">

            <p class="text-sm text-slate-600">
                Your own account must remain an active admin.
            </p>
        @endif

        @unless ($editing)
            <x-form.input name="password" label="Password — at least 12 characters" type="password"
                autocomplete="new-password" minlength="12" required />

            <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password"
                minlength="12" required />
        @endunless

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit"
                class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                {{ $editing ? 'Save changes' : 'Create user' }}
            </button>

            <a href="{{ route('users.index') }}" class="rounded text-sm text-slate-600 underline">
                Cancel
            </a>
        </div>
    </form>
</x-layouts.app>
