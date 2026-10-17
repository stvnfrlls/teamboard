<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $projects = Project::query()
            ->visibleTo($request->user('web'))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('key', 'like', '%'.$search.'%');
                });
            })
            ->withCount('members')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('projects.index', compact('projects', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', Project::class);

        return $this->form(new Project);
    }

    public function store(
        SaveProjectRequest $request,
        ProjectManager $projects
    ): RedirectResponse {
        $project = $projects->save(
            $request->user('web'),
            $request->validated()
        );

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project created.');
    }

    public function show(Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load([
            'creator:id,name',
            'members:id,name,email,is_active',
        ]);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return $this->form($project);
    }

    public function update(
        SaveProjectRequest $request,
        Project $project,
        ProjectManager $projects
    ): RedirectResponse {
        $project = $projects->save(
            $request->user('web'),
            $request->validated(),
            $project
        );

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project updated.');
    }

    protected function form(Project $project): View
    {
        return view('projects.form', [
            'project' => $project,
            'availableMembers' => User::query()
                ->select(['id', 'name', 'email', 'is_active'])
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            'selectedMemberIds' => $project->exists
                ? $project->members()->pluck('users.id')->all()
                : [],
        ]);
    }
}
