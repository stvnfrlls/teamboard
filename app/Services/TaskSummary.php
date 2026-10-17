<?php

namespace App\Services;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;

class TaskSummary
{
    public function for(Builder $tasks): array
    {
        $totals = (clone $tasks)
            ->reorder()
            ->select('tasks.status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('tasks.status')
            ->pluck('total', 'status');

        $statusCounts = [];

        foreach (TaskStatus::cases() as $status) {
            $statusCounts[$status->value] = (int) ($totals[$status->value] ?? 0);
        }

        $total = array_sum($statusCounts);
        $done = $statusCounts[TaskStatus::Done->value];

        return [
            'total' => $total,
            'open' => $total - $done,
            'done' => $done,
            'overdue' => (clone $tasks)->overdue()->count(),
            'due_today' => (clone $tasks)->dueToday()->count(),
            'completion_percentage' => $total === 0
                ? 0
                : (int) floor($done * 100 / $total),
            'status_counts' => $statusCounts,
        ];
    }
}
