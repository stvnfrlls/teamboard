<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserAccountRules
{
    public static function for(?User $user = null): array
    {
        $emailRule = Rule::unique('users', 'email');

        if ($user) {
            $emailRule->ignore($user);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $emailRule],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => $user
                ? ['prohibited']
                : ['required', 'string', Password::min(12), 'confirmed'],
        ];
    }
}
