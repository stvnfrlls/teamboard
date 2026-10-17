<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectManager
{
    public function save(
        User $actor,
        array $data,
        ?Project $project = null
    ): Project {
        return DB::transaction(function () use ($actor, $data, $project) {
            $actor = User::query()
                ->whereKey($actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($project !== null) {
                $project = Project::query()
                    ->whereKey($project->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                Gate::forUser($actor)->authorize('update', $project);

                $project->fill(Arr::only($data, [
                    'name',
                    'description',
                ]));
            } else {
                Gate::forUser($actor)->authorize('create', Project::class);

                $project = new Project(Arr::only($data, [
                    'name',
                    'key',
                    'description',
                ]));

                $project->created_by = $actor->getKey();
            }

            $project->save();

            $project->members()->sync($data['member_ids']);

            return $project;
        }, 3);
    }
}
