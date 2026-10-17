<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $actor = User::query()
                ->whereKey($actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('create', User::class);

            return User::create(Arr::only($data, [
                'name',
                'email',
                'password',
                'role',
                'is_active',
            ]));
        }, 3);
    }

    public function update(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data) {
            $accounts = User::query()
                ->whereIn('id', [$actor->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $actor = $accounts->get($actor->getKey());
            $target = $accounts->get($target->getKey());

            abort_unless($actor && $target, 404);

            Gate::forUser($actor)->authorize('update', $target);

            if ($actor->is($target)) {
                $errors = [];

                if ($data['role'] !== UserRole::Admin->value) {
                    $errors['role'] = 'You cannot remove your own admin role.';
                }

                if (! (bool) $data['is_active']) {
                    $errors['is_active'] = 'You cannot deactivate your own account.';
                }

                if ($errors) {
                    throw ValidationException::withMessages($errors);
                }
            }

            $target->fill(Arr::only($data, [
                'name',
                'email',
                'role',
                'is_active',
            ]));

            $target->save();

            return $target;
        }, 3);
    }
}
