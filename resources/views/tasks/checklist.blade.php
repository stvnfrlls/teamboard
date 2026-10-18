<section id="checklist" aria-labelledby="checklist-heading" class="ui-card mt-6 scroll-mt-24 p-6 sm:p-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="checklist-heading" class="text-lg font-semibold text-slate-900">
            Checklist
        </h2>

        <p class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
            {{ $task->completed_checklist_items_count }}
            of {{ $task->checklist_items_count }} complete
        </p>
    </div>

    <progress value="{{ $task->completed_checklist_items_count }}" max="{{ max(1, $task->checklist_items_count) }}"
        aria-label="Checklist completion" class="mt-5 h-2.5 w-full overflow-hidden rounded-full accent-emerald-600">
    </progress>

    @error('lock_version')
        <div role="alert" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p>{{ $message }}</p>

            <a href="{{ route('projects.tasks.show', [
                'project' => $project,
                'task' => $task,
                'checklist_page' => $checklistItems->currentPage(),
            ]) }}#checklist"
                class="ui-button ui-button-secondary mt-3">
                Reload checklist
            </a>
        </div>
    @enderror

    @error('is_completed')
        <p role="alert" class="mt-4 text-sm text-red-700">{{ $message }}</p>
    @enderror

    @can('update', $task)
        <form method="POST" action="{{ route('projects.tasks.checklist.store', [$project, $task]) }}"
            class="mt-6 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-end sm:p-5">
            @csrf

            <div class="min-w-0 flex-1">
                <x-form.input name="label" label="New checklist item" maxlength="255" required />
            </div>

            <button type="submit" class="ui-button ui-button-primary">
                Add item
            </button>
        </form>
    @endcan

    <p class="mt-6 text-xs text-slate-500">Newest items first.</p>

    <ul class="mt-3 space-y-3">
        @forelse ($checklistItems as $item)
            <li class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
                <div class="min-w-0 flex-1">
                    <p @class([
                        'break-words text-sm leading-6 font-medium',
                        'text-slate-500 line-through' => $item->is_completed,
                        'text-slate-800' => !$item->is_completed,
                    ])>{{ $item->label }}</p>

                    <p @class([
                        'mt-1 text-xs',
                        'text-emerald-700' => $item->is_completed,
                        'text-slate-500' => !$item->is_completed,
                    ])>
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
                            class="ui-button ui-button-secondary">
                            {{ $item->is_completed ? 'Reopen' : 'Mark done' }}
                        </button>
                    </form>
                @endcan
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-slate-200 px-5 py-10 text-center text-sm text-slate-500">
                No checklist items yet.
            </li>
        @endforelse
    </ul>

    @if ($checklistItems->hasPages())
        <div class="mt-6">
            {{ $checklistItems->links() }}
        </div>
    @endif
</section>
