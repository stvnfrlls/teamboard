<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $actor = $request->user('web');

        $assigned = Task::query()
            ->visibleTo($actor)
            ->assignedTo($actor);

        $totals = (clone $assigned)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = [];

        foreach (TaskStatus::cases() as $status) {
            $statusCounts[$status->value] = (int) ($totals[$status->value] ?? 0);
        }

        $done = $statusCounts[TaskStatus::Done->value];

        $summary = [
            'open' => array_sum($statusCounts) - $done,
            'done' => $done,
            'overdue' => (clone $assigned)->overdue()->count(),
            'due_today' => (clone $assigned)->dueToday()->count(),
            'projects' => Project::query()->visibleTo($actor)->count(),
        ];

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
            'statusCounts' => $statusCounts,
            'taskStatuses' => TaskStatus::cases(),
            'tasks' => $tasks,
        ]);
    }
}
