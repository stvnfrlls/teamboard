<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TaskSummary $summaries): View
    {
        $actor = $request->user('web');

        $assigned = Task::query()
            ->visibleTo($actor)
            ->assignedTo($actor);

        $taskSummary = $summaries->for($assigned);

        $summary = Arr::only($taskSummary, [
            'open',
            'done',
            'overdue',
            'due_today',
        ]);

        $summary['projects'] = Project::query()->visibleTo($actor)->count();

        $tasks = (clone $assigned)
            ->open()
            ->with([
                'project:id,key,name',
                'assignee:id,name,is_active',
            ])
            ->byDueDate()
            ->limit(5)
            ->get();

        return view('dashboard', [
            'summary' => $summary,
            'statusCounts' => $taskSummary['status_counts'],
            'taskStatuses' => TaskStatus::cases(),
            'tasks' => $tasks,
        ]);
    }
}
