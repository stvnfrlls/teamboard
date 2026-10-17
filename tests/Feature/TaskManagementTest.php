<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskChecklistItem;
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

    public function test_status_changes_preserve_other_fields_and_existing_assignment(): void
    {
        $assignee = User::factory()->create();
        $this->project->members()->attach($assignee);

        $task = $this->createTask([
            'title' => 'Keep this title',
            'description' => 'Keep this description',
            'priority' => TaskPriority::High->value,
            'assignee_id' => $assignee->id,
            'due_date' => '2026-12-01',
        ]);

        $assignee->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->patchJson(
                route('projects.tasks.status', [$this->project, $task]),
                [
                    'status' => TaskStatus::Done->value,
                    'lock_version' => 0,
                    'title' => 'Unexpected replacement',
                    'assignee_id' => null,
                    'due_date' => null,
                ]
            )
            ->assertOk()
            ->assertJsonPath('task.status', TaskStatus::Done->value)
            ->assertJsonPath('task.lock_version', 1);

        $task->refresh();

        $this->assertSame('Keep this title', $task->title);
        $this->assertSame('Keep this description', $task->description);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame($assignee->id, $task->assignee_id);
        $this->assertSame('2026-12-01', $task->due_date->format('Y-m-d'));
        $this->assertNotNull($task->completed_at);
    }

    public function test_stale_status_changes_are_rejected(): void
    {
        $task = $this->createTask();
        $url = route('projects.tasks.status', [$this->project, $task]);

        $this->actingAs($this->admin)
            ->patchJson($url, [
                'status' => TaskStatus::InReview->value,
                'lock_version' => 0,
            ])
            ->assertOk();

        $this->patchJson($url, [
            'status' => TaskStatus::Done->value,
            'lock_version' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_version');

        $task->refresh();

        $this->assertSame(TaskStatus::InReview, $task->status);
        $this->assertSame(1, $task->lock_version);
        $this->assertNull($task->completed_at);
    }

    public function test_status_changes_require_project_access_and_correct_project_binding(): void
    {
        $task = $this->createTask();
        $url = route('projects.tasks.status', [$this->project, $task]);
        $data = [
            'status' => TaskStatus::InProgress->value,
            'lock_version' => 0,
        ];

        $this->patchJson($url, $data)->assertUnauthorized();

        $member = User::factory()->create();

        $this->actingAs($member)
            ->patchJson($url, $data)
            ->assertForbidden();

        $this->project->members()->attach($member);

        $this->patchJson($url, $data)->assertOk();

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(
                route('projects.tasks.status', [$otherProject, $task]),
                [
                    'status' => TaskStatus::Done->value,
                    'lock_version' => 1,
                ]
            )
            ->assertNotFound();

        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
    }

    public function test_status_changes_validate_status_and_revision(): void
    {
        $task = $this->createTask();
        $url = route('projects.tasks.status', [$this->project, $task]);

        $this->actingAs($this->admin);

        foreach (['invalid', ['done']] as $status) {
            $this->patchJson($url, [
                'status' => $status,
                'lock_version' => 0,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->patchJson($url, [
            'status' => TaskStatus::Done->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lock_version');

        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
        $this->assertSame(0, $task->fresh()->lock_version);
    }

    public function test_board_columns_have_independent_pagination(): void
    {
        $done = $this->createTask([
            'title' => 'Completed board task',
            'status' => TaskStatus::Done->value,
        ]);

        $firstTodo = null;

        for ($number = 0; $number < 21; $number++) {
            $task = $this->createTask([
                'title' => 'Board todo '.$number,
            ]);

            $firstTodo ??= $task;
        }

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.index', [
                'project' => $this->project,
                'view' => 'board',
            ]))
            ->assertOk()
            ->assertSeeText($done->title)
            ->assertDontSeeText($firstTodo->title)
            ->assertViewHas('columns', function (array $columns): bool {
                return $columns['todo']['tasks']->total() === 21
                    && $columns['todo']['tasks']->count() === 20
                    && $columns['done']['tasks']->total() === 1
                    && $columns['done']['tasks']->count() === 1;
            });

        $this->get(route('projects.tasks.index', [
            'project' => $this->project,
            'view' => 'board',
            'todo_page' => 2,
        ]))
            ->assertOk()
            ->assertSeeText($firstTodo->title)
            ->assertSeeText($done->title)
            ->assertViewHas('columns', function (array $columns): bool {
                return $columns['todo']['tasks']->currentPage() === 2
                    && $columns['todo']['tasks']->count() === 1
                    && $columns['done']['tasks']->currentPage() === 1
                    && $columns['done']['tasks']->count() === 1;
            });
    }

    public function test_board_filters_keep_results_inside_the_project(): void
    {
        $matching = $this->createTask([
            'title' => 'Board needle match',
            'priority' => TaskPriority::High->value,
        ]);

        $this->createTask([
            'title' => 'Board needle wrong priority',
            'priority' => TaskPriority::Low->value,
        ]);

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->createTask([
            'title' => 'Board needle other project',
            'priority' => TaskPriority::High->value,
        ], $otherProject);

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.index', [
                'project' => $this->project,
                'view' => 'board',
                'search' => 'needle',
                'priority' => TaskPriority::High->value,
            ]))
            ->assertOk()
            ->assertSeeText($matching->title)
            ->assertDontSeeText('Board needle wrong priority')
            ->assertDontSeeText('Board needle other project')
            ->assertViewHas('columns', function (array $columns): bool {
                $total = array_sum(array_map(
                    fn (array $column) => $column['tasks']->total(),
                    $columns
                ));

                return $total === 1;
            });
    }

    public function test_board_requires_project_access(): void
    {
        $url = route('projects.tasks.index', [
            'project' => $this->project,
            'view' => 'board',
        ]);

        $this->get($url)->assertRedirect(route('login'));

        $member = User::factory()->create();

        $this->actingAs($member)->get($url)->assertForbidden();

        $this->project->members()->attach($member);

        $this->get($url)
            ->assertOk()
            ->assertViewHas('viewMode', 'board');
    }

    public function test_status_menu_works_without_javascript(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->patch(
                route('projects.tasks.status', [$this->project, $task]),
                [
                    'status' => TaskStatus::Done->value,
                    'lock_version' => 0,
                ]
            )
            ->assertRedirect(route('projects.tasks.index', [
                'project' => $this->project,
                'view' => 'board',
            ]))
            ->assertSessionHas('status', 'Task status updated.');

        $task->refresh();

        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame(1, $task->lock_version);
        $this->assertNotNull($task->completed_at);
    }

    public function test_members_can_comment_without_changing_the_task_or_forging_authorship(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $task = $this->createTask();
        $otherTask = $this->createTask();
        $updatedAt = $task->getRawOriginal('updated_at');
        $version = $task->lock_version;

        $this->travel(1)->minutes();

        $this->actingAs($member)
            ->post(
                route('projects.tasks.comments.store', [$this->project, $task]),
                [
                    'body' => "  First line\nSecond line  ",
                    'author_id' => $this->admin->id,
                    'task_id' => $otherTask->id,
                    'created_at' => '2000-01-01 00:00:00',
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('projects.tasks.show', [$this->project, $task]).'#comments'
            )
            ->assertSessionHas('status', 'Comment added.');

        $comment = $task->comments()->sole();

        $this->assertSame($member->id, $comment->author_id);
        $this->assertSame($task->id, $comment->task_id);
        $this->assertSame("First line\nSecond line", $comment->body);
        $this->assertNotSame('2000-01-01 00:00:00', $comment->getRawOriginal('created_at'));
        $this->assertSame(0, $otherTask->comments()->count());

        $task->refresh();

        $this->assertSame($version, $task->lock_version);
        $this->assertSame($updatedAt, $task->getRawOriginal('updated_at'));
    }

    public function test_admins_can_comment_without_membership_and_comment_html_is_escaped(): void
    {
        $task = $this->createTask();
        $body = '<script>alert("comment")</script>';

        $this->actingAs($this->admin)
            ->post(
                route('projects.tasks.comments.store', [$this->project, $task]),
                ['body' => $body]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee(e($body), false)
            ->assertDontSee($body, false)
            ->assertSeeText($this->admin->name);

        $this->assertSame($body, $task->comments()->sole()->body);
    }

    public function test_guests_nonmembers_and_inactive_users_cannot_post_comments(): void
    {
        $task = $this->createTask();
        $url = route('projects.tasks.comments.store', [$this->project, $task]);

        $this->post($url, ['body' => 'Guest comment'])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->post($url, ['body' => 'Outsider comment'])
            ->assertForbidden();

        $inactive = User::factory()->create(['is_active' => false]);
        $this->project->members()->attach($inactive);

        $this->actingAs($inactive)
            ->post($url, ['body' => 'Inactive comment'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, $task->comments()->count());
    }

    public function test_removed_members_cannot_read_or_add_comments(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);
        $task = $this->createTask();

        $this->actingAs($member)
            ->post(
                route('projects.tasks.comments.store', [$this->project, $task]),
                ['body' => 'Comment before removal']
            )
            ->assertRedirect();

        $this->project->members()->detach($member);

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertForbidden();

        $this->post(
            route('projects.tasks.comments.store', [$this->project, $task]),
            ['body' => 'Comment after removal']
        )->assertForbidden();

        $this->assertSame(1, $task->comments()->count());
    }

    public function test_comments_cannot_be_posted_through_another_projects_url(): void
    {
        $task = $this->createTask();
        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(
                route('projects.tasks.comments.store', [$otherProject, $task]),
                ['body' => 'Wrong project']
            )
            ->assertNotFound();

        $this->assertSame(0, $task->comments()->count());
    }

    public function test_invalid_comments_are_rejected_and_forms_render_safely(): void
    {
        $task = $this->createTask();
        $showUrl = route('projects.tasks.show', [$this->project, $task]);
        $storeUrl = route('projects.tasks.comments.store', [$this->project, $task]);

        $this->actingAs($this->admin);

        foreach ([null, '', " \n\t ", ['invalid'], str_repeat('a', 5001)] as $body) {
            $this->from($showUrl)
                ->post($storeUrl, ['body' => $body])
                ->assertRedirect($showUrl)
                ->assertSessionHasErrors('body');

            $this->get($showUrl)->assertOk();
        }

        $this->assertSame(0, $task->comments()->count());
    }

    public function test_comments_are_paginated_newest_first_and_scoped_to_the_task(): void
    {
        $task = $this->createTask();
        $otherTask = $this->createTask();
        $manager = app(TaskManager::class);

        for ($number = 1; $number <= 21; $number++) {
            $manager->addComment(
                $this->admin,
                $this->project,
                $task,
                sprintf('Comment entry [%02d]', $number)
            );
        }

        $manager->addComment(
            $this->admin,
            $this->project,
            $otherTask,
            'Private to the other task'
        );

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSeeText('Comment entry [21]')
            ->assertDontSeeText('Comment entry [01]')
            ->assertDontSeeText('Private to the other task')
            ->assertViewHas('comments', function ($comments): bool {
                return $comments->total() === 21
                    && $comments->count() === 20
                    && $comments->first()->body === 'Comment entry [21]';
            });

        $this->get(route('projects.tasks.show', [
            'project' => $this->project,
            'task' => $task,
            'comments_page' => 2,
        ]))
            ->assertOk()
            ->assertSeeText('Comment entry [01]')
            ->assertDontSeeText('Comment entry [21]')
            ->assertDontSeeText('Private to the other task')
            ->assertViewHas('comments', function ($comments): bool {
                return $comments->currentPage() === 2
                    && $comments->count() === 1;
            });
    }

    public function test_comment_writes_recheck_the_actors_current_database_access(): void
    {
        $task = $this->createTask();
        $staleAdmin = $this->admin;

        User::query()
            ->whereKey($staleAdmin->id)
            ->update(['role' => 'member']);

        $this->actingAs($staleAdmin)
            ->post(
                route('projects.tasks.comments.store', [$this->project, $task]),
                ['body' => 'Access was revoked']
            )
            ->assertForbidden();

        $this->assertSame(0, $task->comments()->count());
    }

    public function test_members_can_add_checklist_items_with_server_controlled_metadata(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $task = $this->createTask();
        $otherTask = $this->createTask();
        $updatedAt = $task->getRawOriginal('updated_at');

        $this->travel(1)->minutes();

        $this->actingAs($member)
            ->post(
                route('projects.tasks.checklist.store', [$this->project, $task]),
                [
                    'label' => '  Review permissions  ',
                    'created_by' => $this->admin->id,
                    'task_id' => $otherTask->id,
                    'completed_at' => '2000-01-01 00:00:00',
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('projects.tasks.show', [$this->project, $task]).'#checklist'
            );

        $item = $task->checklistItems()->sole();

        $this->assertSame('Review permissions', $item->label);
        $this->assertSame($member->id, $item->created_by);
        $this->assertSame($task->id, $item->task_id);
        $this->assertFalse($item->is_completed);
        $this->assertNull($item->completed_at);
        $this->assertSame(0, $item->lock_version);
        $this->assertSame(0, $otherTask->checklistItems()->count());
        $this->assertSame(0, $task->fresh()->lock_version);
        $this->assertSame($updatedAt, $task->fresh()->getRawOriginal('updated_at'));
    }

    public function test_checklist_completion_preserves_time_until_reopened(): void
    {
        $task = $this->createTask();
        $item = $this->createChecklistItem($task);
        $url = route('projects.tasks.checklist.update', [$this->project, $task, $item]);

        $this->actingAs($this->admin);

        $this->patch($url, ['is_completed' => true, 'lock_version' => 0])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $item->refresh();
        $completedAt = $item->completed_at;

        $this->assertTrue($item->is_completed);
        $this->assertNotNull($completedAt);

        $this->travel(1)->minutes();

        $this->patch($url, ['is_completed' => true, 'lock_version' => 1])
            ->assertSessionHasNoErrors();

        $this->assertEquals($completedAt, $item->fresh()->completed_at);

        $this->patch($url, ['is_completed' => false, 'lock_version' => 2])
            ->assertSessionHasNoErrors();

        $this->assertFalse($item->fresh()->is_completed);
        $this->assertNull($item->fresh()->completed_at);

        $this->travel(1)->minutes();

        $this->patch($url, ['is_completed' => true, 'lock_version' => 3])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $task->refresh();

        $this->assertTrue($item->completed_at->greaterThan($completedAt));
        $this->assertSame(4, $item->lock_version);
        $this->assertSame(0, $task->lock_version);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertNull($task->completed_at);
    }

    public function test_checklist_updates_reject_stale_versions_and_invalid_changes(): void
    {
        $task = $this->createTask();
        $item = $this->createChecklistItem($task);
        $url = route('projects.tasks.checklist.update', [$this->project, $task, $item]);

        $this->actingAs($this->admin)
            ->patchJson($url, ['is_completed' => true, 'lock_version' => 0])
            ->assertRedirect();

        $cases = [
            [['is_completed' => false, 'lock_version' => 0], 'lock_version'],
            [['is_completed' => false], 'lock_version'],
            [['is_completed' => false, 'lock_version' => -1], 'lock_version'],
            [['is_completed' => ['invalid'], 'lock_version' => 1], 'is_completed'],
            [['lock_version' => 1], 'is_completed'],
            [[
                'is_completed' => false,
                'lock_version' => 1,
                'label' => 'Unexpected rename',
            ], 'label'],
        ];

        foreach ($cases as [$data, $field]) {
            $this->patchJson($url, $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $item->refresh();

        $this->assertTrue($item->is_completed);
        $this->assertSame(1, $item->lock_version);
        $this->assertSame('Review behavior', $item->label);
    }

    public function test_checklist_creation_validates_labels_and_rejects_client_state(): void
    {
        $task = $this->createTask();
        $showUrl = route('projects.tasks.show', [$this->project, $task]);
        $storeUrl = route('projects.tasks.checklist.store', [$this->project, $task]);

        $this->actingAs($this->admin);

        foreach ([null, '', " \n ", ['invalid'], str_repeat('a', 256)] as $label) {
            $this->from($showUrl)
                ->post($storeUrl, ['label' => $label])
                ->assertRedirect($showUrl)
                ->assertSessionHasErrors('label');

            $this->get($showUrl)->assertOk();
        }

        $this->postJson($storeUrl, [
            'label' => 'Valid label',
            'is_completed' => true,
            'lock_version' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_completed', 'lock_version']);

        $this->assertSame(0, $task->checklistItems()->count());
    }

    public function test_checklist_writes_require_active_project_access(): void
    {
        $task = $this->createTask();
        $item = $this->createChecklistItem($task);
        $storeUrl = route('projects.tasks.checklist.store', [$this->project, $task]);
        $updateUrl = route('projects.tasks.checklist.update', [$this->project, $task, $item]);
        $update = ['is_completed' => true, 'lock_version' => 0];

        $this->post($storeUrl, ['label' => 'Guest item'])
            ->assertRedirect(route('login'));
        $this->patch($updateUrl, $update)
            ->assertRedirect(route('login'));

        $member = User::factory()->create();

        $this->actingAs($member)
            ->post($storeUrl, ['label' => 'Outsider item'])
            ->assertForbidden();
        $this->patch($updateUrl, $update)->assertForbidden();

        $this->project->members()->attach($member);
        $this->project->members()->detach($member);

        $this->patch($updateUrl, $update)->assertForbidden();

        $inactive = User::factory()->create(['is_active' => false]);
        $this->project->members()->attach($inactive);

        $this->actingAs($inactive)
            ->post($storeUrl, ['label' => 'Inactive item'])
            ->assertRedirect(route('login'));
        $this->patch($updateUrl, $update)
            ->assertRedirect(route('login'));

        $this->assertSame(1, $task->checklistItems()->count());
        $this->assertFalse($item->fresh()->is_completed);
    }

    public function test_checklist_items_are_scoped_to_their_task_and_project(): void
    {
        $task = $this->createTask();
        $otherTask = $this->createTask();
        $item = $this->createChecklistItem($task);
        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        foreach (
            [
                [$this->project, $otherTask],
                [$otherProject, $task],
            ] as [$project, $parentTask]
        ) {
            $this->patch(
                route('projects.tasks.checklist.update', [$project, $parentTask, $item]),
                ['is_completed' => true, 'lock_version' => 0]
            )->assertNotFound();
        }

        $this->assertFalse($item->fresh()->is_completed);
        $this->assertSame(0, $item->fresh()->lock_version);
    }

    public function test_checklist_writes_recheck_current_database_access(): void
    {
        $task = $this->createTask();
        $item = $this->createChecklistItem($task);
        $staleAdmin = $this->admin;

        User::query()->whereKey($staleAdmin->id)->update(['role' => 'member']);

        $this->actingAs($staleAdmin)
            ->post(
                route('projects.tasks.checklist.store', [$this->project, $task]),
                ['label' => 'Revoked access']
            )
            ->assertForbidden();

        $this->patch(
            route('projects.tasks.checklist.update', [$this->project, $task, $item]),
            ['is_completed' => true, 'lock_version' => 0]
        )->assertForbidden();

        $this->assertSame(1, $task->checklistItems()->count());
        $this->assertFalse($item->fresh()->is_completed);
    }

    public function test_checklist_progress_counts_all_pages_and_labels_are_escaped(): void
    {
        $task = $this->createTask();
        $html = '<script>alert("checklist")</script>';
        $oldest = $this->createChecklistItem($task, $html);
        $manager = app(TaskManager::class);

        $manager->saveChecklistItem(
            $this->admin,
            $this->project,
            $task,
            ['is_completed' => true, 'lock_version' => 0],
            $oldest
        );

        for ($number = 1; $number <= 20; $number++) {
            $this->createChecklistItem($task, 'Pending item '.$number);
        }

        $otherTask = $this->createTask();
        $this->createChecklistItem($otherTask, 'Other task checklist');

        $manager->addComment($this->admin, $this->project, $task, 'Existing discussion');

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertDontSeeText('Other task checklist')
            ->assertViewHas('task', function (Task $shown): bool {
                return (int) $shown->checklist_items_count === 21
                    && (int) $shown->completed_checklist_items_count === 1;
            })
            ->assertViewHas('checklistItems', function ($items): bool {
                return $items->total() === 21 && $items->count() === 20;
            });

        $this->get(route('projects.tasks.show', [
            'project' => $this->project,
            'task' => $task,
            'checklist_page' => 2,
        ]))
            ->assertOk()
            ->assertSee(e($html), false)
            ->assertDontSee($html, false)
            ->assertSeeText('Existing discussion')
            ->assertViewHas('checklistItems', function ($items): bool {
                return $items->currentPage() === 2 && $items->count() === 1;
            })
            ->assertViewHas('comments', function ($comments): bool {
                return $comments->currentPage() === 1 && $comments->count() === 1;
            });
    }

    private function createChecklistItem(
        Task $task,
        string $label = 'Review behavior'
    ): TaskChecklistItem {
        return app(TaskManager::class)->saveChecklistItem(
            $this->admin,
            $this->project,
            $task,
            ['label' => $label]
        );
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
