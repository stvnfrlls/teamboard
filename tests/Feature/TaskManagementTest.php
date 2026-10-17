<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->project = Project::factory()->create([
            'created_by' => $this->admin->id,
            'key' => 'TMMS',
        ]);
    }

    public function test_admins_can_access_task_pages_without_project_membership(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin);

        $this->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSeeText($task->reference);

        $this->get(route('projects.tasks.create', $this->project))
            ->assertOk()
            ->assertSeeText('Create task');

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSeeText($task->title);

        $this->get(route('projects.tasks.edit', [$this->project, $task]))
            ->assertOk()
            ->assertSeeText('Save changes');
    }

    public function test_project_members_can_create_tasks_and_assign_themselves(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $response = $this->actingAs($member)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'assignee_id' => $member->id,
            ]));

        $task = Task::query()->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.tasks.show', [$this->project, $task]));

        $this->assertSame($member->id, (int) $task->created_by);
        $this->assertSame($member->id, (int) $task->assignee_id);
    }

    public function test_nonmembers_cannot_access_or_change_project_tasks(): void
    {
        $task = $this->createTask();
        $outsider = User::factory()->create();

        $this->actingAs($outsider);

        $this->get(route('projects.tasks.index', $this->project))->assertForbidden();
        $this->get(route('projects.tasks.create', $this->project))->assertForbidden();

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertForbidden();

        $this->get(route('projects.tasks.edit', [$this->project, $task]))
            ->assertForbidden();

        $this->post(route('projects.tasks.store', $this->project), $this->taskData())
            ->assertForbidden();

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['title' => 'Unauthorized change'])
        )->assertForbidden();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertSame(0, Task::query()->visibleTo($outsider)->count());
        $this->assertSame($task->title, $task->fresh()->title);
    }

    public function test_guests_cannot_access_task_routes(): void
    {
        $task = $this->createTask();

        $this->get(route('projects.tasks.index', $this->project))
            ->assertRedirect(route('login'));

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertRedirect(route('login'));

        $this->post(route('projects.tasks.store', $this->project), $this->taskData())
            ->assertRedirect(route('login'));

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task)
        )->assertRedirect(route('login'));
    }

    public function test_creation_uses_server_controlled_identity_and_metadata(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'status' => TaskStatus::InProgress->value,
                'priority' => TaskPriority::High->value,
                'assignee_id' => $member->id,
                'project_id' => $otherProject->id,
                'created_by' => $member->id,
                'number' => 999,
                'next_task_number' => 999,
                'completed_at' => '2000-01-01 00:00:00',
            ]))
            ->assertSessionHasNoErrors();

        $task = Task::query()->firstOrFail();

        $this->assertSame($this->project->id, (int) $task->project_id);
        $this->assertSame($this->admin->id, (int) $task->created_by);
        $this->assertSame(1, $task->number);
        $this->assertSame('TMMS-1', $task->reference);
        $this->assertSame(0, $task->lock_version);
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertNull($task->completed_at);
        $this->assertSame(2, $this->project->fresh()->next_task_number);
    }

    public function test_numbers_are_sequential_per_project_and_are_not_reused(): void
    {
        $first = $this->createTask();
        $second = $this->createTask();

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
            'key' => 'OTHER',
        ]);

        $otherTask = $this->createTask(project: $otherProject);

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
        $this->assertSame('OTHER-1', $otherTask->reference);

        $second->delete();

        $third = $this->createTask();

        $this->assertSame(3, $third->number);
        $this->assertSame(4, $this->project->fresh()->next_task_number);
        $this->assertSame(2, $otherProject->fresh()->next_task_number);
    }

    public function test_invalid_assignments_do_not_create_tasks_or_consume_numbers(): void
    {
        $outsider = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $this->project->members()->attach($inactive);

        $this->actingAs($this->admin);

        foreach ([$outsider->id, $inactive->id, $this->admin->id] as $assigneeId) {
            $this->post(
                route('projects.tasks.store', $this->project),
                $this->taskData(['assignee_id' => $assigneeId])
            )->assertSessionHasErrors('assignee_id');
        }

        $this->assertDatabaseCount('tasks', 0);
        $this->assertSame(1, $this->project->fresh()->next_task_number);

        $this->project->members()->attach($outsider);

        $this->post(
            route('projects.tasks.store', $this->project),
            $this->taskData(['assignee_id' => $outsider->id])
        )->assertSessionHasNoErrors();

        $this->assertSame(1, Task::query()->firstOrFail()->number);
    }

    public function test_assignment_options_only_include_active_project_members(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $outsider = User::factory()->create();

        $this->project->members()->attach([$active->id, $inactive->id]);

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.create', $this->project))
            ->assertOk()
            ->assertViewHas('assigneeOptions', function (array $options) use (
                $active,
                $inactive,
                $outsider
            ) {
                return array_key_exists($active->id, $options)
                    && ! array_key_exists($inactive->id, $options)
                    && ! array_key_exists($outsider->id, $options)
                    && ! array_key_exists($this->admin->id, $options);
            });
    }

    public function test_members_can_edit_tasks_and_clear_assignments_without_changing_identity(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $task = $this->createTask(['assignee_id' => $member->id]);

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($member)
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, [
                    'title' => 'Updated task',
                    'assignee_id' => null,
                    'project_id' => $otherProject->id,
                    'created_by' => $member->id,
                    'number' => 999,
                ])
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.tasks.show', [$this->project, $task]));

        $task->refresh();

        $this->assertSame('Updated task', $task->title);
        $this->assertNull($task->assignee_id);
        $this->assertSame($this->project->id, (int) $task->project_id);
        $this->assertSame($this->admin->id, (int) $task->created_by);
        $this->assertSame(1, $task->number);
        $this->assertSame(1, $task->lock_version);
        $this->assertSame(2, $this->project->fresh()->next_task_number);
    }

    public function test_ineligible_existing_assignments_must_be_cleared_or_replaced(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $task = $this->createTask(['assignee_id' => $member->id]);

        $this->project->members()->detach($member);

        $this->actingAs($this->admin);

        $this->get(route('projects.tasks.edit', [$this->project, $task]))
            ->assertOk()
            ->assertViewHas('assigneeOptions', function (array $options) use ($member) {
                return isset($options[$member->id])
                    && str_contains($options[$member->id], 'no longer assignable');
            });

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task)
        )->assertSessionHasErrors('assignee_id');

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['assignee_id' => null])
        )->assertSessionHasNoErrors();

        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_completion_time_is_preserved_until_a_task_is_reopened(): void
    {
        $this->travelTo(now()->startOfSecond());

        $task = $this->createTask();

        $this->assertNull($task->completed_at);
        $this->actingAs($this->admin);

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['status' => TaskStatus::Done->value])
        )->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertNotNull($task->completed_at);
        $firstCompletion = $task->completed_at->getTimestamp();

        $this->travel(1)->hours();

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['description' => 'Updated after completion.'])
        )->assertSessionHasNoErrors();

        $task->refresh();

        $this->assertSame($firstCompletion, $task->completed_at->getTimestamp());

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['status' => TaskStatus::InProgress->value])
        )->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertNull($task->completed_at);

        $this->travel(1)->hours();

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['status' => TaskStatus::Done->value])
        )->assertSessionHasNoErrors();

        $this->assertGreaterThan(
            $firstCompletion,
            $task->fresh()->completed_at->getTimestamp()
        );
    }

    public function test_due_dates_can_be_saved_cleared_and_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'due_date' => '2026-11-20',
            ]))
            ->assertSessionHasNoErrors();

        $task = Task::query()->firstOrFail();

        $this->assertSame('2026-11-20', $task->due_date->format('Y-m-d'));

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['due_date' => null])
        )->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertNull($task->due_date);

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['due_date' => '20/11/2026'])
        )->assertSessionHasErrors('due_date');

        $this->assertSame(1, $task->fresh()->lock_version);
    }

    public function test_stale_edits_are_rejected_and_the_form_preserves_the_submitted_version(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin);

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['title' => 'First save'])
        )->assertSessionHasNoErrors();

        $this->from(route('projects.tasks.edit', [$this->project, $task]))
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, ['title' => 'Stale edit'])
            )
            ->assertRedirect(route('projects.tasks.edit', [$this->project, $task]))
            ->assertSessionHasErrors('lock_version');

        $this->assertSame('First save', $task->fresh()->title);
        $this->assertSame(1, $task->fresh()->lock_version);

        $this->get(route('projects.tasks.edit', [$this->project, $task]))
            ->assertOk()
            ->assertSee('value="0"', false)
            ->assertSee('Stale edit');

        $this->get(route('projects.tasks.edit', [$this->project, $task]))
            ->assertOk()
            ->assertSee('value="1"', false)
            ->assertSee('First save');
    }

    public function test_updates_require_a_version(): void
    {
        $task = $this->createTask();
        $data = $this->editData($task);

        unset($data['lock_version']);

        $this->actingAs($this->admin)
            ->put(route('projects.tasks.update', [$this->project, $task]), $data)
            ->assertSessionHasErrors('lock_version');

        $this->assertSame(0, $task->fresh()->lock_version);
    }

    public function test_creation_rejects_a_client_supplied_version(): void
    {
        $this->actingAs($this->admin)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'lock_version' => 99,
            ]))
            ->assertSessionHasErrors('lock_version');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_tasks_cannot_be_accessed_through_another_projects_url(): void
    {
        $task = $this->createTask();

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        $this->get(route('projects.tasks.show', [$otherProject, $task]))
            ->assertNotFound();

        $this->get(route('projects.tasks.edit', [$otherProject, $task]))
            ->assertNotFound();

        $this->put(
            route('projects.tasks.update', [$otherProject, $task]),
            $this->editData($task, ['title' => 'Should not save'])
        )->assertNotFound();

        $this->assertSame($task->title, $task->fresh()->title);
    }

    public function test_removed_members_lose_access_even_to_tasks_they_created(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $task = $this->createTask(
            ['assignee_id' => $member->id],
            actor: $member
        );

        $this->actingAs($member)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk();

        $this->project->members()->detach($member);

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertForbidden();

        $this->get(route('projects.tasks.index', $this->project))
            ->assertForbidden();

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task)
        )->assertForbidden();
    }

    public function test_task_writes_recheck_the_actors_current_database_access(): void
    {
        $task = $this->createTask();

        User::whereKey($this->admin->id)->update([
            'role' => UserRole::Member->value,
        ]);

        // Deliberately retain the previously loaded admin instance.
        $this->actingAs($this->admin);

        $this->post(route('projects.tasks.store', $this->project), $this->taskData())
            ->assertForbidden();

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['title' => 'Should not save'])
        )->assertForbidden();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertSame($task->title, $task->fresh()->title);
        $this->assertSame(0, $task->fresh()->lock_version);
    }

    public function test_search_and_filters_keep_results_inside_the_project(): void
    {
        $matching = $this->createTask([
            'title' => 'Needle matching task',
            'status' => TaskStatus::InReview->value,
            'priority' => TaskPriority::Urgent->value,
        ]);

        $this->createTask([
            'title' => 'Needle wrong priority',
            'status' => TaskStatus::InReview->value,
        ]);

        $this->createTask([
            'title' => 'Needle wrong status',
            'priority' => TaskPriority::Urgent->value,
        ]);

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->createTask([
            'title' => 'Needle outside project',
            'status' => TaskStatus::InReview->value,
            'priority' => TaskPriority::Urgent->value,
        ], project: $otherProject);

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.index', [
                'project' => $this->project,
                'search' => 'Needle',
                'status' => TaskStatus::InReview->value,
                'priority' => TaskPriority::Urgent->value,
            ]))
            ->assertOk()
            ->assertSeeText($matching->title)
            ->assertDontSeeText('Needle wrong priority')
            ->assertDontSeeText('Needle wrong status')
            ->assertDontSeeText('Needle outside project');

        $this->get(route('projects.tasks.index', [
            'project' => $this->project,
            'search' => strtolower($matching->reference),
        ]))
            ->assertOk()
            ->assertSeeText($matching->title)
            ->assertDontSeeText('Needle wrong priority');
    }

    public function test_task_forms_render_errors_for_malformed_inputs(): void
    {
        $this->actingAs($this->admin)
            ->from(route('projects.tasks.create', $this->project))
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'title' => ['invalid'],
                'description' => ['invalid'],
                'status' => ['invalid'],
                'priority' => ['invalid'],
                'assignee_id' => ['invalid'],
                'due_date' => ['invalid'],
                'lock_version' => ['invalid'],
            ]))
            ->assertRedirect(route('projects.tasks.create', $this->project))
            ->assertSessionHasErrors([
                'title',
                'description',
                'status',
                'priority',
                'assignee_id',
                'due_date',
                'lock_version',
            ]);

        $this->get(route('projects.tasks.create', $this->project))
            ->assertOk();

        $this->assertDatabaseCount('tasks', 0);
    }

    private function createTask(
        array $overrides = [],
        ?Project $project = null,
        ?User $actor = null
    ): Task {
        return app(TaskManager::class)->save(
            $actor ?? $this->admin,
            $project ?? $this->project,
            $this->taskData($overrides)
        );
    }

    private function taskData(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Investigate filtering',
            'description' => 'Check the merchant status filter.',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Normal->value,
            'assignee_id' => null,
            'due_date' => null,
        ], $overrides);
    }

    private function editData(Task $task, array $overrides = []): array
    {
        return array_replace([
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'assignee_id' => $task->assignee_id,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'lock_version' => $task->lock_version,
        ], $overrides);
    }
}
