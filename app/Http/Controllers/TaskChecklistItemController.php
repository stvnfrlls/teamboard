<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveChecklistItemRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Services\TaskManager;
use Illuminate\Http\RedirectResponse;

class TaskChecklistItemController extends Controller
{
    public function store(
        SaveChecklistItemRequest $request,
        Project $project,
        Task $task,
        TaskManager $tasks
    ): RedirectResponse {
        $tasks->saveChecklistItem(
            $request->user('web'),
            $project,
            $task,
            $request->validated()
        );

        return redirect()->to(
            route('projects.tasks.show', [$project, $task]).'#checklist'
        )->with('status', 'Checklist item added.');
    }

    public function update(
        SaveChecklistItemRequest $request,
        Project $project,
        Task $task,
        TaskChecklistItem $checklistItem,
        TaskManager $tasks
    ): RedirectResponse {
        $tasks->saveChecklistItem(
            $request->user('web'),
            $project,
            $task,
            $request->validated(),
            $checklistItem
        );

        return redirect()->to(
            route('projects.tasks.show', [$project, $task]).'#checklist'
        )->with('status', 'Checklist item updated.');
    }
}
