@props(['members', 'selected' => []])

@php
    $selection = old('member_ids', $selected);
    $selection = is_array($selection) ? array_map('strval', array_filter($selection, 'is_scalar')) : [];

    $memberErrors = collect($errors->get('member_ids'))
        ->merge(collect($errors->get('member_ids.*'))->flatten())
        ->unique();
@endphp

<fieldset aria-describedby="members-help members-errors">
    <legend class="text-sm font-medium text-slate-700">
        Project members
    </legend>

    <p id="members-help" class="mt-2 text-sm text-slate-600">
        Selected members can access this project. Admins already have access to all projects.
        Inactive accounts remain unable to sign in.
    </p>

    <div class="mt-3 max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
        @forelse ($members as $member)
            <label for="member-{{ $member->id }}"
                class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50">
                <input id="member-{{ $member->id }}" type="checkbox" name="member_ids[]" value="{{ $member->id }}"
                    @checked(in_array((string) $member->id, $selection, true))
                    class="mt-1 size-4 shrink-0 accent-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">

                <span class="min-w-0">
                    <span class="block text-sm font-medium text-slate-800">
                        {{ $member->name }}

                        @unless ($member->is_active)
                            <span class="ml-1 text-xs font-normal text-slate-500">
                                Inactive
                            </span>
                        @endunless
                    </span>

                    <span class="block break-all text-sm text-slate-500">
                        {{ $member->email }}
                    </span>
                </span>
            </label>
        @empty
            <p class="px-4 py-6 text-sm text-slate-500">
                No users available.
            </p>
        @endforelse
    </div>

    <div id="members-errors">
        @foreach ($memberErrors as $message)
            <p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @endforeach
    </div>
</fieldset>
