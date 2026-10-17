<x-layouts.app title="Notifications">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-600">
            {{ $notifications->total() }} notifications. Newest first. Times shown in UTC.
        </p>

        @if ($notifications->total() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')

                <button type="submit"
                    class="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Mark all as read
                </button>
            </form>
        @endif
    </div>

    <ul class="divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white">
        @forelse ($notifications as $notification)
            @php
                $isAssignment = $notification->type === \App\Notifications\TaskAssigned::class;
                $taskId = $notification->data['task_id'] ?? null;
                $task = $isAssignment && is_int($taskId) ? $tasks->get($taskId) : null;
            @endphp

            <li @class(['p-5', 'bg-indigo-50/50' => $notification->unread()])>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-sm font-semibold">
                                {{ $isAssignment ? 'You were assigned a task.' : 'Notification' }}
                            </p>

                            @if ($notification->unread())
                                <span
                                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                    Unread
                                </span>
                            @endif
                        </div>

                        @if ($isAssignment)
                            @if ($task)
                                <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}"
                                    class="mt-3 inline-block max-w-full break-words rounded text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    {{ $task->reference }} · {{ $task->title }}
                                </a>

                                <p class="mt-1 break-words text-xs text-slate-500">
                                    {{ $task->project->name }}
                                </p>
                            @else
                                <p class="mt-3 text-sm text-slate-500">
                                    This task is no longer available.
                                </p>
                            @endif
                        @endif

                        <time datetime="{{ $notification->created_at->toIso8601String() }}"
                            class="mt-3 block text-xs text-slate-500">
                            {{ $notification->created_at->format('M j, Y H:i') }}
                        </time>
                    </div>

                    @if ($notification->unread())
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            @method('PATCH')

                            <button type="submit"
                                class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                Mark as read
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @empty
            <li class="px-6 py-12 text-center text-sm text-slate-500">
                No notifications yet.
            </li>
        @endforelse
    </ul>

    @if ($notifications->hasPages())
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</x-layouts.app>
