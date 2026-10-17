<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskManager
{
    public function save(
        User $actor,
        Project $project,
        array $data,
        ?Task $task = null
    ): Task {
        return DB::transaction(function () use ($actor, $project, $data, $task) {
            $changesAssignee = array_key_exists('assignee_id', $data);
            $assigneeId = isset($data['assignee_id'])
                ? (int) $data['assignee_id']
                : null;

            $userIds = array_filter(
                [$actor->getKey(), $assigneeId],
                fn ($id) => $id !== null
            );

            $lockedUsers = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $actor = $lockedUsers->get($actor->getKey());

            abort_unless($actor, 403);

            $project = Project::query()
                ->whereKey($project->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($task !== null) {
                $task = $project->tasks()
                    ->whereKey($task->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                Gate::forUser($actor)->authorize('update', $task);

                if ($task->lock_version !== (int) $data['lock_version']) {
                    throw ValidationException::withMessages([
                        'lock_version' => 'This task was updated by someone else. Reload it before saving again.',
                    ]);
                }
            } else {
                Gate::forUser($actor)->authorize('create', [
                    Task::class,
                    $project,
                ]);
            }

            if ($changesAssignee && $assigneeId !== null) {
                $assignable = $lockedUsers->has($assigneeId)
                    && $project->assignableMembers()
                        ->whereKey($assigneeId)
                        ->exists();

                if (! $assignable) {
                    throw ValidationException::withMessages([
                        'assignee_id' => 'Choose an active member of this project or leave the task unassigned.',
                    ]);
                }
            }

            if ($task === null) {
                $task = new Task;
                $task->project()->associate($project);
                $task->created_by = $actor->getKey();
                $task->number = $project->next_task_number;

                $project->increment('next_task_number');
            } else {
                $task->lock_version++;
            }

            $task->fill(Arr::only($data, [
                'title',
                'description',
                'status',
                'priority',
                'due_date',
            ]));

            if ($changesAssignee) {
                $task->assignee_id = $assigneeId;
            }

            $task->completed_at = $task->status === TaskStatus::Done
                ? ($task->completed_at ?? now())
                : null;

            $task->save();
            $task->setRelation('project', $project);

            if (
                $changesAssignee
                && $assigneeId !== null
                && $assigneeId !== (int) $actor->getKey()
                && ($task->wasRecentlyCreated || $task->wasChanged('assignee_id'))
            ) {
                $lockedUsers->get($assigneeId)->notify(
                    new TaskAssigned((int) $task->getKey())
                );
            }

            return $task;
        }, 3);
    }

    public function addComment(
        User $actor,
        Project $project,
        Task $task,
        string $body
    ): TaskComment {
        return DB::transaction(function () use ($actor, $project, $task, $body) {
            [$actor, $task] = $this->lockTaskFor(
                $actor,
                $project,
                $task,
                'comment'
            );

            $comment = $task->comments()->make([
                'body' => $body,
            ]);

            $comment->author()->associate($actor);
            $comment->save();

            return $comment;
        }, 3);
    }

    public function saveChecklistItem(
        User $actor,
        Project $project,
        Task $task,
        array $data,
        ?TaskChecklistItem $item = null
    ): TaskChecklistItem {
        return DB::transaction(function () use ($actor, $project, $task, $data, $item) {
            [$actor, $task] = $this->lockTaskFor(
                $actor,
                $project,
                $task,
                'update'
            );

            if ($item !== null) {
                $item = $task->checklistItems()
                    ->whereKey($item->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($item->lock_version !== (int) $data['lock_version']) {
                    throw ValidationException::withMessages([
                        'lock_version' => 'This checklist item was updated by someone else. Reload before saving again.',
                    ]);
                }

                $item->is_completed = (bool) $data['is_completed'];
                $item->completed_at = $item->is_completed
                    ? ($item->completed_at ?? now())
                    : null;
                $item->lock_version++;
            } else {
                $item = $task->checklistItems()->make([
                    'label' => $data['label'],
                ]);

                $item->creator()->associate($actor);
            }

            $item->save();

            return $item;
        }, 3);
    }

    /**
     * Call inside a database transaction.
     *
     * @return array{0: User, 1: Task}
     */
    protected function lockTaskFor(
        User $actor,
        Project $project,
        Task $task,
        string $ability
    ): array {
        $actor = User::query()
            ->whereKey($actor->getKey())
            ->lockForUpdate()
            ->first();

        abort_unless($actor, 403);

        $project = Project::query()
            ->whereKey($project->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $task = $project->tasks()
            ->whereKey($task->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        Gate::forUser($actor)->authorize($ability, $task);

        return [$actor, $task];
    }
}
