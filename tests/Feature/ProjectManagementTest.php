<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_access_all_projects_without_membership(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create([
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSeeText($project->name);

        $this->get(route('projects.create'))
            ->assertOk()
            ->assertSeeText('Create project');

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeText($project->key);

        $this->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSeeText('Save changes');
    }

    public function test_members_only_see_assigned_projects_in_lists_and_search(): void
    {
        $member = User::factory()->create();

        $assigned = Project::factory()->create([
            'name' => 'Assigned Project',
            'key' => 'VISIBLE',
        ]);

        $hidden = Project::factory()->create([
            'name' => 'Hidden Project',
            'key' => 'PRIVATE',
        ]);

        $assigned->members()->attach($member);

        $this->actingAs($member);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSeeText($assigned->name)
            ->assertDontSeeText($hidden->name);

        $this->get(route('projects.index', ['search' => 'VISIBLE']))
            ->assertOk()
            ->assertSeeText($assigned->name);

        $this->get(route('projects.index', ['search' => 'PRIVATE']))
            ->assertOk()
            ->assertDontSeeText($hidden->name)
            ->assertSeeText('No projects found');
    }

    public function test_assigned_members_can_view_project_details(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();

        $project->members()->attach($member);

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSeeText($project->key)
            ->assertSeeText($member->email);
    }

    public function test_unassigned_members_cannot_open_project_details(): void
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_members_cannot_manage_even_their_assigned_projects(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();
        $originalName = $project->name;

        $project->members()->attach($member);

        $this->actingAs($member);

        $this->get(route('projects.create'))->assertForbidden();
        $this->get(route('projects.edit', $project))->assertForbidden();

        $this->post(route('projects.store'), $this->projectData())
            ->assertForbidden();

        $this->put(route('projects.update', $project), [
            'name' => 'Unauthorized Change',
            'member_ids' => [],
        ])->assertForbidden();

        $this->assertDatabaseCount('projects', 1);
        $this->assertSame($originalName, $project->fresh()->name);
        $this->assertTrue($project->members()->whereKey($member->id)->exists());
    }

    public function test_guests_cannot_access_project_routes(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.index'))
            ->assertRedirect(route('login'));

        $this->get(route('projects.show', $project))
            ->assertRedirect(route('login'));

        $this->post(route('projects.store'), $this->projectData())
            ->assertRedirect(route('login'));

        $this->put(route('projects.update', $project), [
            'name' => 'Unauthorized Change',
        ])->assertRedirect(route('login'));
    }

    public function test_admins_can_create_projects_with_members_and_a_normalized_key(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('projects.store'), $this->projectData([
                'key' => '  tmms  ',
                'member_ids' => [$member->id],
                'created_by' => $member->id,
            ]));

        $project = Project::where('key', 'TMMS')->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame($admin->id, (int) $project->created_by);
        $this->assertSame([$member->id], $project->members->modelKeys());
        $this->assertDatabaseCount('project_user', 1);
    }

    public function test_duplicate_project_keys_are_rejected_after_normalization(): void
    {
        Project::factory()->create(['key' => 'TMMS']);

        $this->actingAs($this->admin())
            ->from(route('projects.create'))
            ->post(route('projects.store'), $this->projectData([
                'key' => '  tmms  ',
            ]))
            ->assertRedirect(route('projects.create'))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_project_keys_must_start_with_a_letter(): void
    {
        $this->actingAs($this->admin())
            ->post(route('projects.store'), $this->projectData([
                'key' => '123ABC',
            ]))
            ->assertSessionHasErrors('key');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_duplicate_memberships_are_rejected(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('projects.store'), $this->projectData([
                'member_ids' => [$member->id, $member->id],
            ]))
            ->assertSessionHasErrors(['member_ids.0', 'member_ids.1']);

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_user', 0);
    }

    public function test_nonexistent_members_are_rejected(): void
    {
        $admin = $this->admin();
        $missingUserId = User::max('id') + 1000;

        $this->actingAs($admin)
            ->post(route('projects.store'), $this->projectData([
                'member_ids' => [$missingUserId],
            ]))
            ->assertSessionHasErrors('member_ids.0');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_admins_can_update_project_details_and_replace_memberships(): void
    {
        $admin = $this->admin();
        $oldMember = User::factory()->create();
        $newMember = User::factory()->create();

        $project = Project::factory()->create([
            'created_by' => $admin->id,
        ]);

        $originalKey = $project->key;
        $project->members()->attach($oldMember);

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'name' => 'Updated Project',
                'description' => 'Updated description.',
                'member_ids' => [$newMember->id],
                'created_by' => $newMember->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $project->refresh();

        $this->assertSame('Updated Project', $project->name);
        $this->assertSame('Updated description.', $project->description);
        $this->assertSame($originalKey, $project->key);
        $this->assertSame($admin->id, (int) $project->created_by);
        $this->assertSame([$newMember->id], $project->members->modelKeys());
        $this->assertDatabaseCount('project_user', 1);
    }

    public function test_omitting_member_ids_removes_all_memberships(): void
    {
        $project = Project::factory()->create();
        $project->members()->attach(User::factory()->create());

        $this->actingAs($this->admin())
            ->put(route('projects.update', $project), [
                'name' => 'Updated Project',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(0, $project->members()->count());
    }

    public function test_project_keys_cannot_be_changed(): void
    {
        $project = Project::factory()->create();
        $originalName = $project->name;
        $originalKey = $project->key;

        $this->actingAs($this->admin())
            ->put(route('projects.update', $project), [
                'name' => 'Should Not Save',
                'key' => 'CHANGED',
                'member_ids' => [],
            ])
            ->assertSessionHasErrors('key');

        $project->refresh();

        $this->assertSame($originalName, $project->name);
        $this->assertSame($originalKey, $project->key);
    }

    public function test_removed_members_lose_project_access(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->create();

        $project->members()->attach($member);

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertOk();

        $this->actingAs($this->admin())
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'member_ids' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertForbidden();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertDontSeeText($project->name);
    }

    public function test_project_writes_recheck_the_admins_current_database_role(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create([
            'created_by' => $admin->id,
        ]);

        $originalName = $project->name;

        User::whereKey($admin->id)->update([
            'role' => UserRole::Member->value,
        ]);

        // Deliberately retain the previously loaded admin instance.
        $this->actingAs($admin);

        $this->post(route('projects.store'), $this->projectData())
            ->assertForbidden();

        $this->put(route('projects.update', $project), [
            'name' => 'Should Not Save',
            'member_ids' => [],
        ])->assertForbidden();

        $this->assertDatabaseCount('projects', 1);
        $this->assertSame($originalName, $project->fresh()->name);
    }

    public function test_project_forms_render_errors_for_malformed_array_inputs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('projects.create'))
            ->post(route('projects.store'), $this->projectData([
                'name' => ['invalid'],
                'key' => ['invalid'],
                'description' => ['invalid'],
                'member_ids' => [[$admin->id]],
            ]))
            ->assertRedirect(route('projects.create'))
            ->assertSessionHasErrors([
                'name',
                'key',
                'description',
                'member_ids.0',
            ]);

        $this->get(route('projects.create'))->assertOk();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_inactive_members_cannot_access_assigned_projects(): void
    {
        $member = User::factory()->create([
            'is_active' => false,
        ]);

        $project = Project::factory()->create();
        $project->members()->attach($member);

        $this->actingAs($member)
            ->get(route('projects.show', $project))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, Project::query()->visibleTo($member)->count());
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    private function projectData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Merchant Management',
            'key' => 'TMMS',
            'description' => 'Merchant management project.',
            'member_ids' => [],
        ], $overrides);
    }
}
