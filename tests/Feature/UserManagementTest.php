<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_user_management_pages(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSeeText($member->email);

        $this->get(route('users.create'))
            ->assertOk()
            ->assertSeeText('Create user');

        $this->get(route('users.edit', $member))
            ->assertOk()
            ->assertSeeText('Save changes');
    }

    public function test_members_cannot_manage_users_or_promote_themselves(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($member);

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->get(route('users.edit', $target))->assertForbidden();

        $this->post(route('users.store'), $this->creationData())
            ->assertForbidden();

        $this->put(route('users.update', $target), $this->accountData())
            ->assertForbidden();

        $this->put(route('users.update', $member), $this->accountData([
            'role' => UserRole::Admin->value,
        ]))->assertForbidden();

        $this->assertDatabaseCount('users', 2);
        $this->assertFalse($member->fresh()->isAdmin());
    }

    public function test_guests_cannot_manage_users(): void
    {
        $user = User::factory()->create();

        $this->get(route('users.index'))
            ->assertRedirect(route('login'));

        $this->post(route('users.store'), $this->creationData())
            ->assertRedirect(route('login'));

        $this->put(route('users.update', $user), $this->accountData())
            ->assertRedirect(route('login'));
    }

    public function test_admins_can_create_users_with_hashed_passwords(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), $this->creationData())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'newmember@example.test')->firstOrFail();

        $this->assertSame(UserRole::Member, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('TeamboardPass123!', $user->password));
        $this->assertNotSame('TeamboardPass123!', $user->password);
    }

    public function test_duplicate_emails_are_rejected_after_normalization(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $this->actingAs($this->admin())
            ->from(route('users.create'))
            ->post(route('users.store'), $this->creationData([
                'email' => '  EXISTING@EXAMPLE.TEST  ',
            ]))
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_invalid_account_data_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), $this->creationData([
                'role' => 'superuser',
                'is_active' => 'invalid',
                'password' => 'short',
                'password_confirmation' => 'different',
            ]))
            ->assertSessionHasErrors(['role', 'is_active', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admins_can_update_other_accounts_without_changing_passwords(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $originalPassword = $target->password;

        $this->actingAs($admin)
            ->put(route('users.update', $target), $this->accountData([
                'name' => 'Updated Member',
                'email' => 'updated@example.test',
                'role' => UserRole::Admin->value,
                'is_active' => '0',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $target->refresh();

        $this->assertSame('Updated Member', $target->name);
        $this->assertSame('updated@example.test', $target->email);
        $this->assertSame(UserRole::Admin, $target->role);
        $this->assertFalse($target->is_active);
        $this->assertSame($originalPassword, $target->password);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admins_can_edit_their_own_profile(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), $this->accountData([
                'name' => 'Updated Admin',
                'email' => $admin->email,
                'role' => UserRole::Admin->value,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $admin->refresh();

        $this->assertSame('Updated Admin', $admin->name);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
    }

    public function test_admins_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('users.edit', $admin))
            ->put(route('users.update', $admin), $this->accountData([
                'email' => $admin->email,
                'role' => UserRole::Admin->value,
                'is_active' => '0',
            ]))
            ->assertRedirect(route('users.edit', $admin))
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admins_cannot_remove_their_own_admin_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), $this->accountData([
                'email' => $admin->email,
                'role' => UserRole::Member->value,
            ]))
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_account_editing_rejects_password_changes(): void
    {
        $target = User::factory()->create();
        $originalPassword = $target->password;

        $this->actingAs($this->admin())
            ->put(route('users.update', $target), $this->accountData([
                'email' => $target->email,
                'password' => 'ReplacementPass123!',
                'password_confirmation' => 'ReplacementPass123!',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertSame($originalPassword, $target->fresh()->password);
    }

    public function test_updates_recheck_the_admins_current_database_role(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $originalName = $target->name;

        User::whereKey($admin->id)->update([
            'role' => UserRole::Member->value,
        ]);

        // Deliberately use the previously loaded admin instance.
        $this->actingAs($admin)
            ->put(route('users.update', $target), $this->accountData([
                'email' => $target->email,
            ]))
            ->assertForbidden();

        $this->assertSame($originalName, $target->fresh()->name);
    }

    public function test_deactivation_ends_an_existing_login_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        User::whereKey($user->id)->update([
            'is_active' => false,
        ]);

        Auth::forgetGuards();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admins_can_search_users(): void
    {
        User::factory()->create([
            'name' => 'Needle Person',
            'email' => 'needle@example.test',
        ]);

        User::factory()->create([
            'name' => 'Unrelated Person',
            'email' => 'unrelated@example.test',
        ]);

        $this->actingAs($this->admin())
            ->get(route('users.index', ['search' => 'needle']))
            ->assertOk()
            ->assertSeeText('needle@example.test')
            ->assertDontSeeText('unrelated@example.test');
    }

    public function test_forms_can_render_errors_for_invalid_array_inputs(): void
    {
        $this->actingAs($this->admin())
            ->from(route('users.create'))
            ->post(route('users.store'), $this->creationData([
                'name' => ['invalid'],
                'email' => ['invalid'],
                'role' => ['invalid'],
                'is_active' => ['invalid'],
            ]))
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['name', 'email', 'role', 'is_active']);

        $this->get(route('users.create'))->assertOk();
    }

    public function test_admin_command_creates_an_active_admin(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Workspace Admin')
            ->expectsQuestion('Email address', '  ADMIN@EXAMPLE.TEST  ')
            ->expectsQuestion('Password (at least 12 characters)', 'TeamboardPass123!')
            ->expectsQuestion('Confirm password', 'TeamboardPass123!')
            ->assertExitCode(0);

        $user = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('TeamboardPass123!', $user->password));
    }

    public function test_admin_command_rejects_existing_emails(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Another Admin')
            ->expectsQuestion('Email address', 'EXISTING@EXAMPLE.TEST')
            ->expectsQuestion('Password (at least 12 characters)', 'TeamboardPass123!')
            ->expectsQuestion('Confirm password', 'TeamboardPass123!')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    private function accountData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'New Member',
            'email' => 'newmember@example.test',
            'role' => UserRole::Member->value,
            'is_active' => '1',
        ], $overrides);
    }

    private function creationData(array $overrides = []): array
    {
        return array_replace($this->accountData(), [
            'password' => 'TeamboardPass123!',
            'password_confirmation' => 'TeamboardPass123!',
        ], $overrides);
    }
}
