<x-layouts.app title="Users">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3">
            <x-form.input name="search" label="Search users" type="search" :value="$search" maxlength="100"
                placeholder="Name or email" />

            <button type="submit"
                class="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Search
            </button>

            @if ($search !== '')
                <a href="{{ route('users.index') }}" class="rounded px-2 py-2.5 text-sm text-indigo-700 underline">
                    Clear
                </a>
            @endif
        </form>

        @can('create', \App\Models\User::class)
            <a href="{{ route('users.create') }}"
                class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Add user
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <caption class="sr-only">Team accounts</caption>

            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th scope="col" class="px-4 py-3 font-medium">Name</th>
                    <th scope="col" class="px-4 py-3 font-medium">Email</th>
                    <th scope="col" class="px-4 py-3 font-medium">Role</th>
                    <th scope="col" class="px-4 py-3 font-medium">Status</th>
                    <th scope="col" class="px-4 py-3 font-medium">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr>
                        <th scope="row" class="px-4 py-4 font-medium">
                            {{ $user->name }}

                            @if ($user->is(auth()->user()))
                                <span class="text-xs font-normal text-slate-500">(You)</span>
                            @endif
                        </th>

                        <td class="px-4 py-4 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-4">{{ $user->role->label() }}</td>

                        <td class="px-4 py-4">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-emerald-50 text-emerald-700' => $user->is_active,
                                'bg-slate-100 text-slate-600' => !$user->is_active,
                            ])>
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            @can('update', $user)
                                <a href="{{ route('users.edit', $user) }}" aria-label="Edit {{ $user->name }}"
                                    class="rounded font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    Edit
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-slate-500">
                            No users match your search.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</x-layouts.app>
