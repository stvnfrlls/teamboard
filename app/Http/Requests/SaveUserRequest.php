<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\UserAccountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        $actor = $this->user('web');

        return $target instanceof User
            ? ($actor?->can('update', $target) ?? false)
            : ($actor?->can('create', User::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge([
                'email' => Str::lower(trim($email)),
            ]);
        }
    }

    public function rules(): array
    {
        return UserAccountRules::for($this->route('user'));
    }
}
