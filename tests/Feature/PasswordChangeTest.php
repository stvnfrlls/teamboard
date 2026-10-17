<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_pages_require_an_active_login(): void
    {
        $this->get(route('password.edit'))
            ->assertRedirect(route('login'));

        $this->put(route('password.update'), $this->passwordData())
            ->assertRedirect(route('login'));

        foreach ([UserRole::Member, UserRole::Admin] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('password.edit'))
                ->assertOk()
                ->assertSeeText('Change password and sign out')
                ->assertSee(route('password.update'), false);
        }

        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($inactive)
            ->get(route('password.edit'))
            ->assertRedirect(route('login'));

        $this->actingAs($inactive)
            ->put(route('password.update'), $this->passwordData())
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('password', $inactive->fresh()->password));
    }

    public function test_password_changes_only_affect_the_current_account_and_revoke_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $oldToken = $user->remember_token;
        $otherHash = $other->password;

        $firstSession = $this->createSession($user);
        $secondSession = $this->createSession($user);
        $otherSession = $this->createSession($other);

        $this->actingAs($user)
            ->withSession(['private_marker' => 'discard-me'])
            ->put(route('password.update'), $this->passwordData([
                'user_id' => $other->id,
                'id' => $other->id,
                'role' => UserRole::Admin->value,
                'is_active' => false,
                'email' => $other->email,
            ]))
            ->assertRedirect(route('login'))
            ->assertSessionHas(
                'status',
                'Password changed. Sign in with your new password.'
            )
            ->assertSessionMissing('private_marker');

        $fresh = $user->fresh();

        $this->assertGuest();
        $this->assertTrue(Hash::check('updated-password-123', $fresh->password));
        $this->assertFalse(Hash::check('password', $fresh->password));
        $this->assertNotSame($oldToken, $fresh->remember_token);
        $this->assertSame(UserRole::Member, $fresh->role);
        $this->assertTrue($fresh->is_active);
        $this->assertSame($user->email, $fresh->email);
        $this->assertSame($otherHash, $other->fresh()->password);

        $this->assertDatabaseMissing('sessions', ['id' => $firstSession]);
        $this->assertDatabaseMissing('sessions', ['id' => $secondSession]);
        $this->assertDatabaseHas('sessions', ['id' => $otherSession]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('Password changed. Sign in with your new password.');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'updated-password-123',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($fresh);
    }

    public function test_invalid_password_changes_preserve_credentials_and_do_not_flash_secrets(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $oldToken = $user->remember_token;

        $cases = [
            [[], ['current_password', 'password']],
            [$this->passwordData(['current_password' => []]), ['current_password']],
            [$this->passwordData([
                'password' => [],
                'password_confirmation' => [],
            ]), ['password']],
            [$this->passwordData([
                'password' => 'short',
                'password_confirmation' => 'short',
            ]), ['password']],
            [$this->passwordData([
                'password_confirmation' => 'does-not-match',
            ]), ['password']],
            [$this->passwordData([
                'current_password' => 'incorrect-password',
            ]), ['current_password']],
        ];

        $this->actingAs($user);

        foreach ($cases as [$data, $errors]) {
            $this->from(route('password.edit'))
                ->put(route('password.update'), $data)
                ->assertRedirect(route('password.edit'))
                ->assertSessionHasErrors($errors)
                ->assertSessionMissing('_old_input.current_password')
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');

            $this->get(route('password.edit'))
                ->assertOk()
                ->assertDontSee('updated-password-123', false)
                ->assertDontSee('incorrect-password', false);
        }

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertSame($oldToken, $user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_new_password_must_differ_from_the_current_password(): void
    {
        $password = 'existing-password-123';
        $user = User::factory()->create(['password' => $password]);
        $oldHash = $user->password;

        $this->actingAs($user)
            ->put(route('password.update'), $this->passwordData([
                'current_password' => $password,
                'password' => $password,
                'password_confirmation' => $password,
            ]))
            ->assertSessionHasErrors('password');

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_writes_recheck_current_credentials_and_account_status(): void
    {
        $staleUser = User::factory()->create();

        $staleUser->fresh()->update([
            'password' => 'database-password-123',
        ]);

        $currentHash = $staleUser->fresh()->password;

        try {
            app(UserManager::class)->changePassword(
                $staleUser,
                'password',
                'updated-password-123'
            );

            $this->fail('Stale credentials should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_password', $exception->errors());
        }

        User::query()->whereKey($staleUser->id)->update([
            'is_active' => false,
        ]);

        try {
            app(UserManager::class)->changePassword(
                $staleUser,
                'database-password-123',
                'updated-password-123'
            );

            $this->fail('Inactive accounts should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame($currentHash, $staleUser->fresh()->password);
    }

    public function test_password_token_and_session_changes_roll_back_together(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $oldToken = $user->remember_token;
        $sessionId = $this->createSession($user);

        try {
            DB::transaction(function () use ($user, $sessionId) {
                app(UserManager::class)->changePassword(
                    $user,
                    'password',
                    'updated-password-123'
                );

                $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);

                throw new \RuntimeException('Rollback password change.');
            });

            $this->fail('The transaction should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback password change.', $exception->getMessage());
        }

        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertSame($oldToken, $user->fresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
    }

    public function test_protected_pages_reject_an_old_session_password_hash(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;

        $user->update(['password' => 'updated-password-123']);

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $oldHash])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_password_changes_are_rate_limited_until_the_window_expires(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->put(route('password.update'), $this->passwordData([
                'current_password' => 'incorrect-password',
            ]))->assertSessionHasErrors('current_password');
        }

        $this->put(route('password.update'), $this->passwordData())
            ->assertStatus(429);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $this->travel(61)->seconds();

        $this->put(route('password.update'), $this->passwordData())
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(Hash::check(
            'updated-password-123',
            $user->fresh()->password
        ));
    }

    private function passwordData(array $overrides = []): array
    {
        return array_replace([
            'current_password' => 'password',
            'password' => 'updated-password-123',
            'password_confirmation' => 'updated-password-123',
        ], $overrides);
    }

    private function createSession(User $user): string
    {
        $id = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        return $id;
    }
}
