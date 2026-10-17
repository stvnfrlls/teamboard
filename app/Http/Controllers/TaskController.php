<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\SaveTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'view' => ['nullable', Rule::in(['list', 'board'])],
        ]);

        $search = trim($filters['search'] ?? '');
        $filters['search'] = $search;

        $number = Str::startsWith(Str::upper($search), $project->key.'-')
            ? Str::after(Str::upper($search), $project->key.'-')
            : $search;

        $query = $project->tasks()->getQuery()
            ->when($search !== '', function (Builder $query) use ($search, $number) {
                $query->where(function (Builder $query) use ($search, $number) {
                    $query->where('title', 'like', '%'.$search.'%');

                    if (ctype_digit($number)) {
                        $query->orWhere('number', $number);
                    }
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, $status) {
                $query->where('status', $status);
            })
            ->when($filters['priority'] ?? null, function (Builder $query, $priority) {
                $query->where('priority', $priority);
            })
            ->with([
                'project:id,key',
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

        return view('tasks.show', compact('project', 'task'));
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
