<x-layouts.app title="Notifications">
    <section aria-labelledby="inbox-heading" class="ui-card mb-6 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="inbox-heading" class="text-lg font-semibold text-slate-900">
                    Your inbox
                </h2>

                <p class="mt-1 text-sm leading-6 text-slate-500">
                    {{ $notifications->total() }} notifications.
                    Newest first. Times shown in UTC.
                </p>
            </div>

            @if ($notifications->total() > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    @method('PATCH')

                    <button type="submit" class="ui-button ui-button-secondary">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </section>

    <ul class="space-y-4">
        @forelse ($notifications as $notification)
            @php
                $isAssignment = $notification->type === \App\Notifications\TaskAssigned::class;
                $taskId = $notification->data['task_id'] ?? null;
                $task = $isAssignment && is_int($taskId) ? $tasks->get($taskId) : null;
            @endphp

            <li @class([
                'ui-card p-5 sm:p-6',
                'border-indigo-200 bg-indigo-50/50' => $notification->unread(),
            ])>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $isAssignment ? 'You were assigned a task.' : 'Notification' }}
                            </p>

                            @if ($notification->unread())
                                <span
                                    class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                                    Unread
                                </span>
                            @endif
                        </div>

                        @if ($isAssignment)
                            @if ($task)
                                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}"
                                    class="ui-link mt-3 inline-block max-w-full break-words text-sm leading-6">
                                    {{ $task->reference }} · {{ $task->title }}
                                </a>

                                <p class="mt-2 break-words text-xs text-slate-500">
                                    {{ $task->project->name }}
                                </p>
                            @else
                                <p class="mt-3 text-sm text-slate-500">
                                    This task is no longer available.
                                </p>
                            @endif
                        @endif

                        <time datetime="{{ $notification->created_at->toIso8601String() }}"
                            class="mt-4 block text-xs text-slate-500">
                            {{ $notification->created_at->format('M j, Y H:i') }}
                        </time>
                    </div>

                    @if ($notification->unread())
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            @method('PATCH')

                            <button type="submit" class="ui-button ui-button-secondary">
                                Mark as read
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @empty
            <li class="ui-card px-6 py-16 text-center">
                <p class="font-semibold text-slate-800">No notifications yet.</p>
                <p class="mt-2 text-sm text-slate-500">
                    New task assignments will appear here.
                </p>
            </li>
        @endforelse
    </ul>

    @if ($notifications->hasPages())
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-layouts.app>
