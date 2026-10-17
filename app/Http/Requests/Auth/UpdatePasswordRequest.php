<?php

namespace App\Http\Requests\Auth;

use App\Support\UserAccountRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->is_active ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                ...UserAccountRules::password(),
                'different:current_password',
            ],
        ];
    }
}
