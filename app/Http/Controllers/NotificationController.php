<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Notifications\TaskAssigned;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user('web');

        $notifications = $actor->notifications()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $taskIds = $notifications->getCollection()
            ->filter(
                fn (DatabaseNotification $notification): bool => $notification->type === TaskAssigned::class
            )
            ->pluck('data.task_id')
            ->filter(fn ($id): bool => is_int($id) && $id > 0)
            ->unique()
            ->values()
            ->all();

        $tasks = Task::query()
            ->visibleTo($actor)
            ->whereKey($taskIds)
            ->with('project:id,key,name')
            ->get()
            ->keyBy('id');

        return view('notifications.index', compact('notifications', 'tasks'));
    }

    public function markRead(
        Request $request,
        string $notification
    ): RedirectResponse {
        $request->user('web')->notifications()
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return redirect()->route('notifications.index')
            ->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user('web')->unreadNotifications()
            ->update(['read_at' => now()]);

        return redirect()->route('notifications.index')
            ->with('status', 'All notifications marked as read.');
    }
}
