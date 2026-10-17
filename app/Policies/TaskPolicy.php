<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('view', $project);
    }

    public function view(User $user, Task $task): bool
    {
        return Task::query()
            ->visibleTo($user)
            ->whereKey($task->getKey())
            ->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $this->viewAny($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
