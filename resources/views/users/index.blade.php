<x-layouts.app title="Users">
    <section aria-labelledby="accounts-heading" class="ui-card mb-6 p-5 sm:p-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="accounts-heading" class="text-lg font-semibold text-slate-900">
                    Team accounts
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Manage account details, roles, and access.
                </p>
            </div>

            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create') }}" class="ui-button ui-button-primary">
                    <span aria-hidden="true">+</span>
                    Add user
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('users.index') }}"
            class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-5">
            <div class="w-full min-w-0 sm:w-auto sm:max-w-md sm:flex-1">
                <x-form.input name="search" label="Search users" type="search" :value="$search" maxlength="100"
                    placeholder="Name or email" />
            </div>

            <button type="submit" class="ui-button ui-button-primary">
                Search
            </button>

            @if ($search !== '')
                <a href="{{ route('users.index') }}" class="ui-button ui-button-secondary">
                    Clear
                </a>
            @endif
        </form>
    </section>

    <p class="mb-4 text-sm text-slate-500">
        {{ $users->total() }} matching accounts
    </p>

    <div class="ui-table-frame ui-card overflow-x-auto">
        <table role="table" class="ui-responsive-table min-w-full text-left text-sm">
            <caption class="sr-only">Team accounts</caption>

            <thead role="rowgroup"
                class="sr-only bg-slate-50 text-xs tracking-wide text-slate-500 uppercase md:not-sr-only">
                <tr role="row">
                    <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Name</th>
                    <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Email</th>
                    <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Role</th>
                    <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Status</th>
                    <th role="columnheader" scope="col" class="px-5 py-4 font-semibold">Actions</th>
                </tr>
            </thead>

            <tbody role="rowgroup" class="md:divide-y md:divide-slate-100">
                @forelse ($users as $user)
                    <tr role="row" class="transition-colors hover:bg-slate-50/80">
                        <th role="rowheader" scope="row"
                            class="font-semibold text-slate-900 md:min-w-40 md:max-w-xs md:px-5 md:py-5">
                            <span aria-hidden="true" class="ui-table-label">Name</span>

                            <div class="ui-cell-value">
                                {{ $user->name }}

                                @if ($user->is(auth()->user()))
                                    <span class="ml-1 text-xs font-normal text-slate-500">(You)</span>
                                @endif
                            </div>
                        </th>

                        <td role="cell" class="text-slate-500 md:min-w-56 md:max-w-sm md:px-5 md:py-5">
                            <span aria-hidden="true" class="ui-table-label">Email</span>

                            <div class="ui-cell-value">
                                {{ $user->email }}
                            </div>
                        </td>

                        <td role="cell" class="md:px-5 md:py-5">
                            <span aria-hidden="true" class="ui-table-label">Role</span>

                            <div class="ui-cell-value">
                                <span
                                    class="inline-flex rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                    {{ $user->role->label() }}
                                </span>
                            </div>
                        </td>

                        <td role="cell" class="md:px-5 md:py-5">
                            <span aria-hidden="true" class="ui-table-label">Status</span>

                            <div class="ui-cell-value">
                                <span @class([
                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                                    'bg-emerald-50 text-emerald-700' => $user->is_active,
                                    'bg-slate-100 text-slate-600' => !$user->is_active,
                                ])>
                                    <span aria-hidden="true" class="size-1.5 shrink-0 rounded-full bg-current"></span>
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </td>

                        <td role="cell" class="md:px-5 md:py-5">
                            <span aria-hidden="true" class="ui-table-label">Actions</span>

                            <div class="ui-cell-value">
                                @can('update', $user)
                                    <a href="{{ route('users.edit', $user) }}" aria-label="Edit {{ $user->name }}"
                                        class="ui-button ui-button-secondary">
                                        Edit
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="5"
                            class="ui-table-empty-cell text-slate-500 md:px-6 md:py-16 md:text-center">
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
