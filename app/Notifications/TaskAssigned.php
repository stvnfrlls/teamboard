<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    public function __construct(public readonly int $taskId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'task_id' => $this->taskId,
        ];
    }
}
