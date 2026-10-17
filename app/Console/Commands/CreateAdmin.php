<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserAccountRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an active administrator account';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Name')),
            'email' => Str::lower(trim((string) $this->ask('Email address'))),
            'password' => (string) $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => (string) $this->secret('Confirm password'),
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ];

        $validator = Validator::make($data, UserAccountRules::for());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->validated());

        $this->info('Admin account created for '.$user->email.'.');

        return self::SUCCESS;
    }
}
