<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskCommentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskManager;
use Illuminate\Http\RedirectResponse;

class TaskCommentController extends Controller
{
    public function store(
        StoreTaskCommentRequest $request,
        Project $project,
        Task $task,
        TaskManager $tasks
    ): RedirectResponse {
        $tasks->addComment(
            $request->user('web'),
            $project,
            $task,
            $request->validated('body')
        );

        return redirect()->to(
            route('projects.tasks.show', [$project, $task]).'#comments'
        )->with('status', 'Comment added.');
    }
}
