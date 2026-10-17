<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
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

            if ($assigneeId !== null) {
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

            $task->assignee_id = $assigneeId;

            $task->completed_at = $task->status === TaskStatus::Done
                ? ($task->completed_at ?? now())
                : null;

            $task->save();
            $task->setRelation('project', $project);

            return $task;
        }, 3);
    }
}
