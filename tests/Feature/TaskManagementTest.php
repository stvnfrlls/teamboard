<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\TaskManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_personal_pages_only_include_accessible_assignments(): void
    {
        $member = User::factory()->create();
        $otherUser = User::factory()->create();

        $own = $this->createAssignedTask($member, ['title' => 'Visible assignment']);
        $this->createAssignedTask($otherUser, ['title' => 'Someone else assignment']);
        $this->createTask(['title' => 'Unassigned task']);

        $secondProject = Project::factory()->create([
            'key' => 'SECOND',
            'created_by' => $this->admin->id,
        ]);

        $second = $this->createAssignedTask(
            $member,
            ['title' => 'Second project assignment'],
            $secondProject
        );

        $lostProject = Project::factory()->create([
            'key' => 'LOST',
            'created_by' => $this->admin->id,
        ]);

        $this->createAssignedTask(
            $member,
            ['title' => 'Assignment after access removed'],
            $lostProject
        );

        $lostProject->members()->detach($member);

        $this->actingAs($member)
            ->get(route('tasks.mine'))
            ->assertOk()
            ->assertSeeText($own->title)
            ->assertSeeText($second->title)
            ->assertDontSeeText('Someone else assignment')
            ->assertDontSeeText('Unassigned task')
            ->assertDontSeeText('Assignment after access removed')
            ->assertSee(route('projects.tasks.show', [$secondProject, $second]), false)
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 2);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Someone else assignment')
            ->assertDontSeeText('Assignment after access removed')
            ->assertViewHas(
                'summary',
                fn (array $summary) => $summary['open'] === 2 && $summary['projects'] === 2
            );
    }

    public function test_admin_personal_pages_only_show_their_own_assignments(): void
    {
        $own = $this->createAssignedTask($this->admin, ['title' => 'Admin assignment']);

        $this->createAssignedTask(
            User::factory()->create(),
            ['title' => 'Member assignment']
        );

        $this->project->members()->detach($this->admin);

        $this->actingAs($this->admin)
            ->get(route('tasks.mine'))
            ->assertOk()
            ->assertSeeText($own->title)
            ->assertDontSeeText('Member assignment')
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 1);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText($own->title)
            ->assertDontSeeText('Member assignment')
            ->assertViewHas('summary', fn (array $summary) => $summary['open'] === 1);
    }

    public function test_dashboard_counts_and_deadline_filters_follow_utc_date_boundaries(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 23:59:00', 'UTC'));

        $member = User::factory()->create();

        $overdue = $this->createAssignedTask($member, [
            'due_date' => '2026-10-16',
        ]);

        $today = $this->createAssignedTask($member, [
            'status' => TaskStatus::InProgress->value,
            'due_date' => '2026-10-17',
        ]);

        $this->createAssignedTask($member, [
            'status' => TaskStatus::InReview->value,
            'due_date' => '2026-10-18',
        ]);

        $this->createAssignedTask($member);

        foreach (['2026-10-16', '2026-10-17'] as $date) {
            $this->createAssignedTask($member, [
                'status' => TaskStatus::Done->value,
                'due_date' => $date,
            ]);
        }

        for ($number = 0; $number < 3; $number++) {
            $this->createAssignedTask($member, ['due_date' => '2026-10-20']);
        }

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('summary', [
                'open' => 7,
                'done' => 2,
                'overdue' => 1,
                'due_today' => 1,
                'projects' => 1,
            ])
            ->assertViewHas('statusCounts', [
                TaskStatus::Todo->value => 5,
                TaskStatus::InProgress->value => 1,
                TaskStatus::InReview->value => 1,
                TaskStatus::Done->value => 2,
            ])
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->count() === 5
                    && $tasks->every(fn (Task $task) => $task->status !== TaskStatus::Done)
            );

        $this->get(route('tasks.mine', ['due' => 'overdue']))
            ->assertOk()
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->getCollection()->modelKeys() === [$overdue->id]
            );

        $this->get(route('tasks.mine', ['due' => 'today']))
            ->assertOk()
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->getCollection()->modelKeys() === [$today->id]
            );

        $this->travel(2)->minutes();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas(
                'summary',
                fn (array $summary) => $summary['overdue'] === 2
                    && $summary['due_today'] === 1
                    && $summary['done'] === 2
            );
    }

    public function test_personal_tasks_are_ordered_by_due_date_with_undated_tasks_last(): void
    {
        $member = User::factory()->create();

        $undated = $this->createAssignedTask($member);
        $late = $this->createAssignedTask($member, ['due_date' => '2026-10-20']);
        $early = $this->createAssignedTask($member, ['due_date' => '2026-10-16']);
        $sameDateOlder = $this->createAssignedTask($member, ['due_date' => '2026-10-17']);
        $sameDateNewer = $this->createAssignedTask($member, ['due_date' => '2026-10-17']);
        $middle = $this->createAssignedTask($member, ['due_date' => '2026-10-19']);

        $expected = [
            $early->id,
            $sameDateNewer->id,
            $sameDateOlder->id,
            $middle->id,
            $late->id,
            $undated->id,
        ];

        $this->actingAs($member)
            ->get(route('tasks.mine'))
            ->assertOk()
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->getCollection()->modelKeys() === $expected
            );

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->modelKeys() === array_slice($expected, 0, 5)
            );
    }

    public function test_my_tasks_filters_and_references_work_across_projects(): void
    {
        $member = User::factory()->create();

        $matching = $this->createAssignedTask($member, [
            'title' => 'Needle matching task',
            'status' => TaskStatus::InReview->value,
            'priority' => TaskPriority::High->value,
        ]);

        $this->createAssignedTask($member, [
            'title' => 'Needle wrong status',
            'priority' => TaskPriority::High->value,
        ]);

        $this->createAssignedTask($member, [
            'title' => 'Needle wrong priority',
            'status' => TaskStatus::InReview->value,
            'priority' => TaskPriority::Low->value,
        ]);

        $otherProject = Project::factory()->create([
            'key' => 'OTHER',
            'created_by' => $this->admin->id,
        ]);

        $other = $this->createAssignedTask(
            $member,
            ['title' => 'Separate project reference'],
            $otherProject
        );

        $this->actingAs($member)
            ->get(route('tasks.mine', [
                'search' => 'Needle',
                'status' => TaskStatus::InReview->value,
                'priority' => TaskPriority::High->value,
            ]))
            ->assertOk()
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->getCollection()->modelKeys() === [$matching->id]
            );

        foreach ([$matching, $other] as $task) {
            $this->get(route('tasks.mine', [
                'search' => strtolower($task->reference),
            ]))
                ->assertOk()
                ->assertViewHas(
                    'tasks',
                    fn ($tasks) => $tasks->getCollection()->modelKeys() === [$task->id]
                );
        }
    }

    public function test_personal_and_project_lists_render_pagination_links(): void
    {
        $member = User::factory()->create();
        $oldest = null;

        for ($number = 1; $number <= 21; $number++) {
            $task = $this->createAssignedTask($member, [
                'title' => sprintf('Paged task [%02d]', $number),
            ]);

            $oldest ??= $task;
        }

        $this->actingAs($member);

        $routes = [
            ['tasks.mine', []],
            ['projects.tasks.index', ['project' => $this->project]],
        ];

        foreach ($routes as [$name, $parameters]) {
            $this->get(route($name, array_merge($parameters, ['search' => 'Paged'])))
                ->assertOk()
                ->assertSee('page=2', false)
                ->assertDontSeeText($oldest->title)
                ->assertViewHas(
                    'tasks',
                    fn ($tasks) => $tasks->total() === 21
                        && $tasks->count() === 20
                        && str_contains($tasks->nextPageUrl(), 'search=Paged')
                );

            $this->get(route($name, array_merge($parameters, [
                'search' => 'Paged',
                'page' => 2,
            ])))
                ->assertOk()
                ->assertSeeText($oldest->title)
                ->assertViewHas('tasks', fn ($tasks) => $tasks->count() === 1);
        }
    }

    public function test_shared_task_filters_reject_invalid_inputs(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $this->actingAs($member);

        $urls = [
            route('tasks.mine'),
            route('projects.tasks.index', $this->project),
        ];

        $cases = [
            ['search', ['invalid']],
            ['search', str_repeat('a', 101)],
            ['status', 'invalid'],
            ['priority', 'invalid'],
            ['due', ['overdue']],
            ['due', 'invalid'],
            ['view', 'invalid'],
        ];

        foreach ($urls as $url) {
            foreach ($cases as [$field, $value]) {
                $this->getJson($url.'?'.http_build_query([$field => $value]))
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }
    }

    public function test_personal_pages_require_active_login_and_handle_empty_assignments(): void
    {
        foreach (['dashboard', 'tasks.mine'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }

        $inactive = User::factory()->create(['is_active' => false]);

        foreach (['dashboard', 'tasks.mine'] as $name) {
            $this->actingAs($inactive)
                ->get(route($name))
                ->assertRedirect(route('login'));
        }

        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('tasks.mine'))
            ->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks->total() === 0);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('No open tasks assigned to you.')
            ->assertViewHas('summary', [
                'open' => 0,
                'done' => 0,
                'overdue' => 0,
                'due_today' => 0,
                'projects' => 0,
            ])
            ->assertViewHas('tasks', fn ($tasks) => $tasks->isEmpty());
    }

    public function test_assignment_notifications_are_stored_and_rendered_safely(): void
    {
        $member = User::factory()->create();
        $title = '<script>alert("assignment")</script>';
        $task = $this->createAssignedTask($member, ['title' => $title]);
        $notification = $member->notifications()->sole();

        $this->assertSame(TaskAssigned::class, $notification->type);
        $this->assertSame(['task_id' => $task->id], $notification->data);
        $this->assertNull($notification->read_at);
        $this->assertSame(0, $this->admin->notifications()->count());

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee(e($title), false)
            ->assertDontSee($title, false)
            ->assertSee('Notifications, 1 unread', false)
            ->assertSee(route('projects.tasks.show', [$this->project, $task]), false)
            ->assertViewHas('notifications', fn ($items) => $items->total() === 1);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unchanged_assignments_status_changes_and_self_assignments_do_not_notify(): void
    {
        $member = User::factory()->create();
        $task = $this->createAssignedTask($member);

        $this->actingAs($this->admin)
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, ['title' => 'Updated title'])
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $task->refresh();

        $this->patchJson(
            route('projects.tasks.status', [$this->project, $task]),
            [
                'status' => TaskStatus::InProgress->value,
                'lock_version' => $task->lock_version,
            ]
        )->assertOk();

        $this->createAssignedTask($this->admin);

        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_reassignment_notifies_only_the_new_assignee(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $task = $this->createAssignedTask($first);

        $this->project->members()->attach($second);
        $manager = app(TaskManager::class);

        $task = $manager->save(
            $this->admin,
            $this->project,
            $this->editData($task, ['assignee_id' => $second->id]),
            $task
        );

        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());

        $task = $manager->save(
            $this->admin,
            $this->project,
            $this->editData($task, ['assignee_id' => null]),
            $task
        );

        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());

        $manager->save(
            $this->admin,
            $this->project,
            $this->editData($task, ['assignee_id' => $second->id]),
            $task
        );

        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(2, $second->notifications()->count());
    }

    public function test_rejected_assignment_changes_do_not_create_notifications(): void
    {
        $member = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $this->project->members()->attach([$member->id, $inactive->id]);
        $task = $this->createTask();

        app(TaskManager::class)->save(
            $this->admin,
            $this->project,
            ['status' => TaskStatus::InProgress->value, 'lock_version' => 0],
            $task
        );

        $this->actingAs($this->admin)
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, ['assignee_id' => $member->id])
            )
            ->assertSessionHasErrors('lock_version');

        $task->refresh();

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['assignee_id' => $inactive->id])
        )->assertSessionHasErrors('assignee_id');

        $this->assertNull($task->fresh()->assignee_id);
        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
    }

    public function test_assignment_notifications_roll_back_with_the_task(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);
        $nextNumber = $this->project->fresh()->next_task_number;

        try {
            DB::transaction(function () use ($member) {
                $this->createTask(['assignee_id' => $member->id]);

                $this->assertSame(1, $member->notifications()->count());

                throw new \RuntimeException('Rollback assignment');
            });

            $this->fail('The transaction should have been rolled back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback assignment', $exception->getMessage());
        }

        $this->assertSame(0, $this->project->tasks()->count());
        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame($nextNumber, $this->project->fresh()->next_task_number);
    }

    public function test_notification_reads_are_owned_scoped_and_idempotent(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->createAssignedTask($member, ['title' => 'First owned alert']);
        $this->createAssignedTask($member, ['title' => 'Second owned alert']);
        $this->createAssignedTask($other, ['title' => 'Other persons alert']);

        $owned = $member->notifications()->firstOrFail();
        $foreign = $other->notifications()->sole();

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSeeText('Other persons alert')
            ->assertSee('Notifications, 2 unread', false)
            ->assertViewHas('notifications', fn ($items) => $items->total() === 2);

        $this->patch(route('notifications.read', $foreign->id))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->patch(route('notifications.read', $owned->id))
            ->assertNotFound();

        $this->actingAs($member)
            ->patch(route('notifications.read', $owned->id))
            ->assertRedirect(route('notifications.index'));

        $readAt = $owned->fresh()->read_at;
        $this->assertNotNull($readAt);
        $this->assertSame(1, $member->unreadNotifications()->count());

        $this->travel(1)->minutes();

        $this->patch(route('notifications.read', $owned->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertEquals($readAt, $owned->fresh()->read_at);

        $this->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $member->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->assertEquals($readAt, $owned->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifications, 0 unread', false);
    }

    public function test_notifications_hide_task_details_after_project_access_is_removed(): void
    {
        $member = User::factory()->create();
        $task = $this->createAssignedTask($member, [
            'title' => 'Confidential task details',
        ]);
        $notification = $member->notifications()->sole();

        $this->project->members()->detach($member);

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('This task is no longer available.')
            ->assertDontSeeText($task->title)
            ->assertDontSeeText($this->project->name)
            ->assertDontSee(route('projects.tasks.show', [$this->project, $task]), false)
            ->assertViewHas('tasks', fn ($tasks) => $tasks->isEmpty());

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertForbidden();

        $this->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notifications_are_paginated_and_unread_counts_cover_all_pages(): void
    {
        $member = User::factory()->create();
        $oldestTask = null;

        for ($number = 1; $number <= 21; $number++) {
            $task = $this->createAssignedTask($member, [
                'title' => sprintf('Notification task [%02d]', $number),
            ]);

            $oldestTask ??= $task;
            $this->travel(1)->seconds();
        }

        $this->createAssignedTask(
            User::factory()->create(),
            ['title' => 'Another inbox task']
        );

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('Notifications, 21 unread', false)
            ->assertDontSeeText($oldestTask->title)
            ->assertDontSeeText('Another inbox task')
            ->assertViewHas(
                'notifications',
                fn ($items) => $items->total() === 21 && $items->count() === 20
            );

        $this->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSeeText($oldestTask->title)
            ->assertSee('Notifications, 21 unread', false)
            ->assertDontSeeText('Another inbox task')
            ->assertViewHas(
                'notifications',
                fn ($items) => $items->currentPage() === 2 && $items->count() === 1
            )
            ->assertViewHas(
                'tasks',
                fn ($tasks) => $tasks->values()->modelKeys() === [$oldestTask->id]
            );
    }

    public function test_notification_routes_require_active_login_and_handle_empty_inboxes(): void
    {
        $member = User::factory()->create();
        $this->createAssignedTask($member);
        $notification = $member->notifications()->sole();

        $routes = [
            ['get', 'notifications.index', []],
            ['patch', 'notifications.read', ['notification' => $notification->id]],
            ['patch', 'notifications.read-all', []],
        ];

        foreach ($routes as [$method, $name, $parameters]) {
            $this->{$method}(route($name, $parameters))
                ->assertRedirect(route('login'));
        }

        $member->update(['is_active' => false]);

        foreach ($routes as [$method, $name, $parameters]) {
            $this->actingAs($member)
                ->{$method}(route($name, $parameters))
                ->assertRedirect(route('login'));
        }

        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs(User::factory()->create())
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('No notifications yet.')
            ->assertSee('Notifications, 0 unread', false)
            ->assertViewHas('notifications', fn ($items) => $items->total() === 0);
    }

    public function test_task_creation_records_the_authenticated_actor(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $this->actingAs($member)
            ->post(route('projects.tasks.store', $this->project), $this->taskData([
                'actor_id' => $this->admin->id,
                'event' => 'forged',
                'changes' => ['title' => ['old' => 'Forged']],
            ]))
            ->assertSessionHasNoErrors();

        $task = Task::query()->sole();
        $activity = $task->activities()->sole();

        $this->assertSame($member->id, (int) $activity->actor_id);
        $this->assertSame('created', $activity->event);
        $this->assertSame([], $activity->changes);
        $this->assertDatabaseCount('task_activities', 1);
    }

    public function test_task_activity_records_changed_values_and_escapes_html(): void
    {
        $member = User::factory()->create([
            'name' => '<b>Assigned member</b>',
        ]);
        $this->project->members()->attach($member);

        $oldTitle = '<script>oldTitle()</script>';
        $newDescription = '<img src=x onerror=alert(1)>';

        $task = $this->createTask([
            'title' => $oldTitle,
            'description' => 'Original description',
        ]);

        $this->actingAs($this->admin)
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, [
                    'title' => 'Updated title',
                    'description' => $newDescription,
                    'status' => TaskStatus::InProgress->value,
                    'priority' => TaskPriority::Urgent->value,
                    'assignee_id' => $member->id,
                    'due_date' => '2026-12-15',
                ])
            )
            ->assertSessionHasNoErrors();

        $activity = $task->activities()->where('event', 'updated')->sole();

        $this->assertSame($this->admin->id, (int) $activity->actor_id);
        $this->assertEquals([
            'title' => [
                'old' => $oldTitle,
                'new' => 'Updated title',
            ],
            'description' => [
                'old' => 'Original description',
                'new' => $newDescription,
            ],
            'status' => [
                'old' => TaskStatus::Todo->value,
                'new' => TaskStatus::InProgress->value,
            ],
            'priority' => [
                'old' => TaskPriority::Normal->value,
                'new' => TaskPriority::Urgent->value,
            ],
            'assignee_id' => [
                'old' => null,
                'new' => $member->id,
            ],
            'due_date' => [
                'old' => null,
                'new' => '2026-12-15',
            ],
        ], $activity->changes);

        $this->assertDatabaseCount('task_activities', 2);

        $this->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSeeText('Activity (2)')
            ->assertSeeText('Previous value')
            ->assertSeeText('Unassigned')
            ->assertSeeText(TaskStatus::InProgress->label())
            ->assertSee(e($oldTitle), false)
            ->assertSee(e($newDescription), false)
            ->assertSee(e($member->name), false)
            ->assertDontSee($oldTitle, false)
            ->assertDontSee($newDescription, false)
            ->assertDontSee($member->name, false);
    }

    public function test_status_activity_preserves_historical_assignments(): void
    {
        $member = User::factory()->create();
        $task = $this->createAssignedTask($member);

        $member->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->patchJson(
                route('projects.tasks.status', [$this->project, $task]),
                [
                    'status' => TaskStatus::Done->value,
                    'lock_version' => $task->lock_version,
                ]
            )
            ->assertOk();

        $activity = $task->activities()->where('event', 'updated')->sole();

        $this->assertEquals([
            'status' => [
                'old' => TaskStatus::Todo->value,
                'new' => TaskStatus::Done->value,
            ],
        ], $activity->changes);

        $this->assertSame($member->id, (int) $task->fresh()->assignee_id);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertDatabaseCount('task_activities', 2);
    }

    public function test_unchanged_saves_comments_and_checklists_do_not_add_task_activity(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task)
            )
            ->assertSessionHasNoErrors();

        $task = $task->fresh();

        $this->patchJson(
            route('projects.tasks.status', [$this->project, $task]),
            [
                'status' => $task->status->value,
                'lock_version' => $task->lock_version,
            ]
        )->assertOk();

        app(TaskManager::class)->addComment(
            $this->admin,
            $this->project,
            $task,
            'A separate discussion.'
        );

        $this->createChecklistItem($task);

        $this->assertSame(1, $task->activities()->count());
        $this->assertSame('created', $task->activities()->sole()->event);
    }

    public function test_rejected_task_changes_do_not_add_activity(): void
    {
        $task = $this->createTask();
        $inactive = User::factory()->create(['is_active' => false]);
        $this->project->members()->attach($inactive);

        $this->actingAs($this->admin)
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, [
                    'title' => 'Stale change',
                    'lock_version' => $task->lock_version + 1,
                ])
            )
            ->assertSessionHasErrors('lock_version');

        $this->put(
            route('projects.tasks.update', [$this->project, $task]),
            $this->editData($task, ['assignee_id' => $inactive->id])
        )->assertSessionHasErrors('assignee_id');

        $this->actingAs(User::factory()->create())
            ->put(
                route('projects.tasks.update', [$this->project, $task]),
                $this->editData($task, ['title' => 'Unauthorized change'])
            )
            ->assertForbidden();

        $this->assertSame($task->title, $task->fresh()->title);
        $this->assertSame($task->lock_version, $task->fresh()->lock_version);
        $this->assertDatabaseCount('task_activities', 1);
    }

    public function test_task_activity_rolls_back_with_creation_and_updates(): void
    {
        $task = $this->createTask();
        $nextNumber = $this->project->fresh()->next_task_number;

        try {
            DB::transaction(function () use ($task) {
                app(TaskManager::class)->save(
                    $this->admin,
                    $this->project,
                    $this->editData($task, ['title' => 'Rolled back title']),
                    $task
                );

                $this->createTask(['title' => 'Rolled back task']);

                $this->assertDatabaseCount('task_activities', 3);

                throw new \RuntimeException('Rollback activity.');
            });

            $this->fail('The transaction should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback activity.', $exception->getMessage());
        }

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('task_activities', 1);
        $this->assertSame($task->title, $task->fresh()->title);
        $this->assertSame(
            $nextNumber,
            $this->project->fresh()->next_task_number
        );
    }

    public function test_activity_is_paginated_newest_first_and_scoped_to_the_task(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $task = $this->createTask();
        $creationId = $task->activities()->sole()->id;

        for ($number = 1; $number <= 20; $number++) {
            $task = app(TaskManager::class)->save(
                $this->admin,
                $this->project,
                $this->editData($task, [
                    'title' => 'Activity revision '.$number,
                ]),
                $task
            );
        }

        $latestId = $task->activities()->max('id');
        $this->createTask(['title' => 'Another task secret']);

        $this->actingAs($member)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee('activity_page=2', false)
            ->assertDontSeeText('Another task secret')
            ->assertViewHas(
                'activities',
                fn ($items) => $items->total() === 21
                    && $items->count() === 20
                    && $items->first()->id === $latestId
                    && $items->getCollection()->every(
                        fn ($item) => (int) $item->task_id === $task->id
                    )
            );

        $this->get(route('projects.tasks.show', [
            'project' => $this->project,
            'task' => $task,
            'activity_page' => 2,
        ]))
            ->assertOk()
            ->assertDontSeeText('Another task secret')
            ->assertViewHas(
                'activities',
                fn ($items) => $items->total() === 21
                    && $items->currentPage() === 2
                    && $items->getCollection()->values()->modelKeys() === [$creationId]
            );
    }

    public function test_activity_requires_current_project_access(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member);
        $task = $this->createTask();

        $url = route('projects.tasks.show', [$this->project, $task]);

        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($member)
            ->get($url)
            ->assertOk()
            ->assertSeeText('created this task.');

        $this->project->members()->detach($member);

        $this->get($url)->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertForbidden();

        $member->update(['is_active' => false]);

        $this->actingAs($member)
            ->get($url)
            ->assertRedirect(route('login'));

        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.show', [$otherProject, $task]))
            ->assertNotFound();

        $this->get($url)
            ->assertOk()
            ->assertSeeText('created this task.');
    }

    public function test_tasks_without_recorded_history_show_an_empty_state(): void
    {
        $task = $this->createTask();

        // Simulate a task that existed before activity logging.
        $task->activities()->delete();

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSeeText('No activity recorded yet.')
            ->assertViewHas('activities', fn ($items) => $items->total() === 0);
    }

    public function test_attachment_uploads_use_private_storage_and_server_metadata(): void
    {
        Storage::fake(TaskAttachment::DISK);
        Storage::fake('public');

        $member = User::factory()->create();
        $this->project->members()->attach($member);
        $task = $this->createTask();
        $updatedAt = $task->updated_at->toDateTimeString();
        $contents = 'Private attachment content.';

        $this->travel(1)->seconds();

        $this->actingAs($member)
            ->post(
                route('projects.tasks.attachments.store', [$this->project, $task]),
                [
                    'attachment' => UploadedFile::fake()
                        ->createWithContent('notes.txt', $contents),
                    'uploaded_by' => $this->admin->id,
                    'task_id' => -99,
                    'original_name' => 'forged.txt',
                    'path' => '../forged.php',
                    'disk' => 'public',
                    'mime_type' => 'text/html',
                    'size' => 999,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('projects.tasks.show', [$this->project, $task]).'#attachments'
            );

        $attachment = $task->attachments()->sole();

        $this->assertSame($member->id, (int) $attachment->uploaded_by);
        $this->assertSame($task->id, (int) $attachment->task_id);
        $this->assertSame('notes.txt', $attachment->original_name);
        $this->assertSame('text/plain', $attachment->mime_type);
        $this->assertSame(strlen($contents), $attachment->size);
        $this->assertNotSame('../forged.php', $attachment->path);
        $this->assertArrayNotHasKey('path', $attachment->toArray());

        Storage::disk(TaskAttachment::DISK)->assertExists($attachment->path);

        $this->assertSame(
            $contents,
            Storage::disk(TaskAttachment::DISK)->get($attachment->path)
        );
        $this->assertCount(1, Storage::disk(TaskAttachment::DISK)->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame($task->lock_version, $task->fresh()->lock_version);
        $this->assertSame(
            $updatedAt,
            $task->fresh()->updated_at->toDateTimeString()
        );
        $this->assertSame(1, $task->activities()->count());
    }

    public function test_members_and_admins_can_download_attachment_contents(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $member = User::factory()->create();
        $this->project->members()->attach($member);
        $task = $this->createTask();
        $contents = 'Download these exact bytes.';
        $attachment = $this->createTaskAttachment($task, 'notes.txt', $contents);

        $url = route('projects.tasks.attachments.download', [
            $this->project,
            $task,
            $attachment,
        ]);

        foreach ([$member, $this->admin] as $actor) {
            $response = $this->actingAs($actor)->get($url);

            $response->assertOk()
                ->assertDownload('notes.txt')
                ->assertHeader('Content-Type', 'application/octet-stream')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertStreamedContent($contents);

            $this->assertStringContainsString(
                'no-store',
                $response->headers->get('Cache-Control')
            );
        }
    }

    public function test_invalid_and_disguised_attachments_are_rejected(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();
        $path = tempnam(sys_get_temp_dir(), 'teamboard-upload-');
        $this->assertNotFalse($path);

        file_put_contents(
            $path,
            '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>'
        );

        $spoofedFile = new UploadedFile(
            $path,
            'preview.png',
            'image/png',
            UPLOAD_ERR_OK,
            true
        );

        $this->assertSame('text/html', $spoofedFile->getMimeType());

        $cases = [
            [],
            ['attachment' => 'not-a-file'],
            ['attachment' => ['invalid']],
            ['attachment' => UploadedFile::fake()->create(
                'large.txt',
                TaskAttachment::MAX_KILOBYTES + 1,
                'text/plain'
            )],
            ['attachment' => UploadedFile::fake()->create(
                'script.php',
                1,
                'text/plain'
            )],
            ['attachment' => $spoofedFile],
        ];

        $this->actingAs($this->admin);

        try {
            foreach ($cases as $data) {
                $this->from(route('projects.tasks.show', [$this->project, $task]))
                    ->post(
                        route('projects.tasks.attachments.store', [$this->project, $task]),
                        $data
                    )
                    ->assertSessionHasErrors('attachment');

                $this->get(route('projects.tasks.show', [$this->project, $task]))
                    ->assertOk()
                    ->assertSee('id="attachment-error"', false)
                    ->assertDontSee('value="not-a-file"', false);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->assertDatabaseCount('task_attachments', 0);
        $this->assertSame([], Storage::disk(TaskAttachment::DISK)->allFiles());
    }

    public function test_supported_attachment_types_are_accepted(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();

        $types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];

        $this->actingAs($this->admin);

        foreach ($types as $extension => $mimeType) {
            $this->post(
                route('projects.tasks.attachments.store', [$this->project, $task]),
                [
                    'attachment' => UploadedFile::fake()
                        ->create('document.'.$extension, 1, $mimeType),
                ]
            )->assertSessionHasNoErrors();
        }

        $this->assertSame(count($types), $task->attachments()->count());
        $this->assertCount(
            count($types),
            Storage::disk(TaskAttachment::DISK)->allFiles()
        );
    }

    public function test_attachment_routes_require_current_active_project_access(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();
        $attachment = $this->createTaskAttachment($task);

        $downloadUrl = route('projects.tasks.attachments.download', [
            $this->project,
            $task,
            $attachment,
        ]);
        $uploadUrl = route('projects.tasks.attachments.store', [$this->project, $task]);

        $this->get($downloadUrl)->assertRedirect(route('login'));
        $this->post($uploadUrl, [
            'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Notes'),
        ])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get($downloadUrl)
            ->assertForbidden();

        $this->post($uploadUrl, [
            'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Notes'),
        ])->assertForbidden();

        $inactive = User::factory()->create(['is_active' => false]);
        $this->project->members()->attach($inactive);

        $this->actingAs($inactive)
            ->get($downloadUrl)
            ->assertRedirect(route('login'));

        $this->actingAs($inactive)
            ->post($uploadUrl, [
                'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Notes'),
            ])
            ->assertRedirect(route('login'));

        $member = User::factory()->create();
        $this->project->members()->attach($member);

        $this->actingAs($member)->get($downloadUrl)->assertOk();

        $this->project->members()->detach($member);

        $this->get($downloadUrl)->assertForbidden();
        $this->post($uploadUrl, [
            'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Notes'),
        ])->assertForbidden();

        $this->assertDatabaseCount('task_attachments', 1);
        $this->assertCount(1, Storage::disk(TaskAttachment::DISK)->allFiles());
    }

    public function test_attachments_are_scoped_to_their_task_and_project_and_handle_missing_files(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();
        $attachment = $this->createTaskAttachment($task);
        $otherTask = $this->createTask();
        $otherProject = Project::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.attachments.download', [
                $this->project,
                $otherTask,
                $attachment,
            ]))
            ->assertNotFound();

        $this->get(route('projects.tasks.attachments.download', [
            $otherProject,
            $task,
            $attachment,
        ]))->assertNotFound();

        $this->post(
            route('projects.tasks.attachments.store', [$otherProject, $task]),
            [
                'attachment' => UploadedFile::fake()
                    ->createWithContent('notes.txt', 'Notes'),
            ]
        )->assertNotFound();

        Storage::disk(TaskAttachment::DISK)->delete($attachment->path);

        $url = route('projects.tasks.attachments.download', [
            $this->project,
            $task,
            $attachment,
        ]);

        $this->get($url)->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertForbidden();

        $this->assertDatabaseCount('task_attachments', 1);
    }

    public function test_attachment_writes_recheck_access_before_storing_files(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();

        $removed = User::factory()->create();
        $this->project->members()->attach($removed);
        $this->project->members()->detach($removed);

        $inactive = User::factory()->create();
        $this->project->members()->attach($inactive);
        User::query()->whereKey($inactive->id)->update(['is_active' => false]);

        $demoted = User::factory()->create(['role' => UserRole::Admin]);
        User::query()->whereKey($demoted->id)->update([
            'role' => UserRole::Member->value,
        ]);

        foreach ([$removed, $inactive, $demoted] as $actor) {
            try {
                app(TaskManager::class)->addAttachment(
                    $actor,
                    $this->project,
                    $task,
                    UploadedFile::fake()->createWithContent('notes.txt', 'Notes')
                );

                $this->fail('Attachment access should be rejected.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('task_attachments', 0);
                $this->assertSame([], Storage::disk(TaskAttachment::DISK)->allFiles());
            }
        }

        $this->assertDatabaseCount('task_attachments', 0);
        $this->assertSame([], Storage::disk(TaskAttachment::DISK)->allFiles());
    }

    public function test_failed_attachment_saves_remove_the_stored_file(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();
        $events = TaskAttachment::getEventDispatcher();

        TaskAttachment::setEventDispatcher(clone $events);

        TaskAttachment::creating(function (): void {
            $this->assertCount(1, Storage::disk(TaskAttachment::DISK)->allFiles());

            throw new \RuntimeException('Simulated attachment save failure.');
        });

        try {
            $this->createTaskAttachment($task);

            $this->fail('The attachment save should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated attachment save failure.',
                $exception->getMessage()
            );
        } finally {
            TaskAttachment::setEventDispatcher($events);
        }

        $this->assertDatabaseCount('task_attachments', 0);
        $this->assertSame([], Storage::disk(TaskAttachment::DISK)->allFiles());
    }

    public function test_attachment_lists_are_paginated_scoped_and_escaped(): void
    {
        Storage::fake(TaskAttachment::DISK);

        $task = $this->createTask();
        $name = '<img src=x onerror=alert(1)>.txt';
        $oldest = $this->createTaskAttachment($task, $name);

        for ($number = 1; $number <= 20; $number++) {
            $this->createTaskAttachment($task, 'notes-'.$number.'.txt');
        }

        $otherTask = $this->createTask();
        $this->createTaskAttachment($otherTask, 'other-task-secret.txt');

        $this->actingAs($this->admin)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee('attachments_page=2', false)
            ->assertDontSeeText('other-task-secret.txt')
            ->assertViewHas(
                'attachments',
                fn ($items) => $items->total() === 21
                    && $items->count() === 20
                    && $items->getCollection()->every(
                        fn ($item) => (int) $item->task_id === $task->id
                    )
            );

        $this->get(route('projects.tasks.show', [
            'project' => $this->project,
            'task' => $task,
            'attachments_page' => 2,
        ]))
            ->assertOk()
            ->assertSee(e($name), false)
            ->assertDontSee($name, false)
            ->assertDontSee($oldest->path, false)
            ->assertDontSeeText('other-task-secret.txt')
            ->assertViewHas(
                'attachments',
                fn ($items) => $items->currentPage() === 2
                    && $items->getCollection()->values()->modelKeys() === [$oldest->id]
            );
    }

    private function createTaskAttachment(
        Task $task,
        string $name = 'notes.txt',
        string $contents = 'Attachment content.'
    ): TaskAttachment {
        return app(TaskManager::class)->addAttachment(
            $this->admin,
            $task->project,
            $task,
            UploadedFile::fake()->createWithContent($name, $contents)
        );
    }

    private function createAssignedTask(
        User $assignee,
        array $overrides = [],
        ?Project $project = null
    ): Task {
        $project ??= $this->project;
        $project->members()->syncWithoutDetaching([$assignee->id]);

        return $this->createTask(
            array_replace($overrides, ['assignee_id' => $assignee->id]),
            $project
        );
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
