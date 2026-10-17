<section id="checklist" aria-labelledby="checklist-heading"
    class="mt-6 scroll-mt-6 rounded-xl border border-slate-200 bg-white p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="checklist-heading" class="text-lg font-semibold">Checklist</h2>

        <p class="text-sm text-slate-600">
            {{ $task->completed_checklist_items_count }}
            of {{ $task->checklist_items_count }} complete
        </p>
    </div>

    <progress value="{{ $task->completed_checklist_items_count }}" max="{{ max(1, $task->checklist_items_count) }}"
        aria-label="Checklist completion" class="mt-3 h-2 w-full accent-indigo-600">
    </progress>

    @error('lock_version')
        <div role="alert" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p>{{ $message }}</p>

            <a href="{{ route('projects.tasks.show', [
                'project' => $project,
                'task' => $task,
                'checklist_page' => $checklistItems->currentPage(),
            ]) }}#checklist"
                class="mt-2 inline-block rounded underline">
                Reload checklist
            </a>
        </div>
    @enderror

    @error('is_completed')
        <p role="alert" class="mt-4 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @can('update', $task)
        <form method="POST" action="{{ route('projects.tasks.checklist.store', [$project, $task]) }}"
            class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf

            <div class="min-w-0 flex-1">
                <x-form.input name="label" label="New checklist item" maxlength="255" required />
            </div>

            <button type="submit"
                class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Add item
            </button>
        </form>
    @endcan

    <p class="mt-6 text-xs text-slate-500">Newest items first.</p>

    <ul class="mt-2 divide-y divide-slate-100">
        @forelse ($checklistItems as $item)
            <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                <div class="min-w-0 flex-1">
                    <p @class([
                        'break-words text-sm',
                        'text-slate-500 line-through' => $item->is_completed,
                        'text-slate-800' => !$item->is_completed,
                    ])>{{ $item->label }}</p>

                    <p class="mt-1 text-xs text-slate-500">
                        {{ $item->is_completed ? 'Complete' : 'Open' }}
                    </p>
                </div>

                @can('update', $task)
                    <form method="POST" action="{{ route('projects.tasks.checklist.update', [$project, $task, $item]) }}">
                        @csrf
                        @method('PATCH')

                        <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                        <input type="hidden" name="is_completed" value="{{ $item->is_completed ? '0' : '1' }}">

                        <button type="submit"
                            aria-label="{{ $item->is_completed ? 'Reopen' : 'Complete' }}: {{ $item->label }}"
                            class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                            {{ $item->is_completed ? 'Reopen' : 'Mark done' }}
                        </button>
                    </form>
                @endcan
            </li>
        @empty
            <li class="py-8 text-center text-sm text-slate-500">
                No checklist items yet.
            </li>
        @endforelse
    </ul>

    @if ($checklistItems->hasPages())
        <div class="mt-5">
            {{ $checklistItems->links() }}
        </div>
    @endif
</section>
