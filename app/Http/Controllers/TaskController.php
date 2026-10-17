<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\SaveTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(ListTasksRequest $request, Project $project): View
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        $filters = $request->validated();
        $filters['search'] = trim($filters['search'] ?? '');

        $query = $project->tasks()->getQuery()
            ->matchingFilters($filters)
            ->with([
                'project:id,key,name',
                'assignee:id,name,is_active',
            ])
            ->orderByDesc('number');

        $viewMode = $filters['view'] ?? 'list';
        $tasks = null;
        $columns = [];

        if ($viewMode === 'board') {
            foreach (TaskStatus::cases() as $status) {
                $columns[$status->value] = [
                    'status' => $status,
                    'tasks' => (clone $query)
                        ->where('status', $status->value)
                        ->paginate(20, ['*'], $status->value.'_page')
                        ->withQueryString(),
                ];
            }
        } else {
            $tasks = $query->paginate(20)->withQueryString();
        }

        return view('tasks.index', [
            'project' => $project,
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => $this->options(TaskStatus::cases()),
            'priorities' => $this->options(TaskPriority::cases()),
            'viewMode' => $viewMode,
            'columns' => $columns,
        ]);
    }

    public function mine(ListTasksRequest $request): View
    {
        $actor = $request->user('web');
        $filters = $request->validated();
        $filters['search'] = trim($filters['search'] ?? '');

        $tasks = Task::query()
            ->visibleTo($actor)
            ->assignedTo($actor)
            ->matchingFilters($filters)
            ->with([
                'project:id,key,name',
                'assignee:id,name,is_active',
            ])
            ->byDueDate()
            ->paginate(20)
            ->withQueryString();

        return view('tasks.mine', [
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => $this->options(TaskStatus::cases()),
            'priorities' => $this->options(TaskPriority::cases()),
        ]);
    }

    public function create(Project $project): View
    {
        Gate::authorize('create', [Task::class, $project]);

        $task = new Task;
        $task->project()->associate($project);

        return $this->form($project, $task);
    }

    public function store(
        SaveTaskRequest $request,
        Project $project,
        TaskManager $tasks
    ): RedirectResponse {
        $task = $tasks->save(
            $request->user('web'),
            $project,
            $request->validated()
        );

        return redirect()->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Task created.');
    }

    public function show(Project $project, Task $task): View
    {
        Gate::authorize('view', $task);

        $task->setRelation('project', $project);

        $task->load([
            'assignee:id,name,email,is_active',
            'creator:id,name',
        ]);

        $comments = $task->comments()
            ->with('author:id,name')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'comments_page')
            ->withQueryString()
            ->fragment('comments');

        $task->loadCount([
            'checklistItems',
            'checklistItems as completed_checklist_items_count' => function (Builder $query) {
                $query->where('is_completed', true);
            },
        ]);

        $checklistItems = $task->checklistItems()
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'checklist_page')
            ->withQueryString()
            ->fragment('checklist');

        $activities = $task->activities()
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'activity_page')
            ->withQueryString()
            ->fragment('activity');

        $assigneeIds = $activities->getCollection()
            ->flatMap(fn ($activity) => [
                data_get($activity->changes, 'assignee_id.old'),
                data_get($activity->changes, 'assignee_id.new'),
            ])
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->unique()
            ->values()
            ->all();

        $activityAssigneeNames = User::query()
            ->whereKey($assigneeIds)
            ->pluck('name', 'id')
            ->all();

        return view('tasks.show', compact(
            'project',
            'task',
            'comments',
            'checklistItems',
            'activities',
            'activityAssigneeNames'
        ));
    }

    public function edit(Project $project, Task $task): View
    {
        Gate::authorize('update', $task);

        $task->setRelation('project', $project);

        return $this->form($project, $task);
    }

    public function update(
        SaveTaskRequest $request,
        Project $project,
        Task $task,
        TaskManager $tasks
    ): RedirectResponse {
        $task = $tasks->save(
            $request->user('web'),
            $project,
            $request->validated(),
            $task
        );

        return redirect()->route('projects.tasks.show', [$project, $task])
            ->with('status', 'Task updated.');
    }

    public function updateStatus(
        UpdateTaskStatusRequest $request,
        Project $project,
        Task $task,
        TaskManager $tasks
    ): JsonResponse|RedirectResponse {
        $task = $tasks->save(
            $request->user('web'),
            $project,
            $request->validated(),
            $task
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Task status updated.',
                'task' => [
                    'id' => $task->getKey(),
                    'status' => $task->status->value,
                    'lock_version' => $task->lock_version,
                ],
            ]);
        }

        return redirect()->route('projects.tasks.index', [
            'project' => $project,
            'view' => 'board',
        ])->with('status', 'Task status updated.');
    }

    protected function form(Project $project, Task $task): View
    {
        $task->load('assignee:id,name,is_active');

        $assignees = $project->assignableMembers()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name']);

        $assigneeOptions = ['' => 'Unassigned'] + $assignees->pluck('name', 'id')->all();

        if (
            $task->assignee_id !== null
            && ! array_key_exists($task->assignee_id, $assigneeOptions)
        ) {
            $assigneeOptions[$task->assignee_id] =
                ($task->assignee?->name ?? 'Previous assignee').' (no longer assignable)';
        }

        return view('tasks.form', [
            'project' => $project,
            'task' => $task,
            'assigneeOptions' => $assigneeOptions,
            'statuses' => $this->options(TaskStatus::cases()),
            'priorities' => $this->options(TaskPriority::cases()),
        ]);
    }

    protected function options(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
