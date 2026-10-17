<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $actor = $this->user('web');

        return $project instanceof Project
            ? ($actor?->can('update', $project) ?? false)
            : ($actor?->can('create', Project::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $key = $this->input('key');

        if (is_string($key)) {
            $this->merge([
                'key' => Str::upper(trim($key)),
            ]);
        }

        $this->mergeIfMissing([
            'member_ids' => [],
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => $this->route('project')
                ? ['prohibited']
                : [
                    'required',
                    'string',
                    'min:2',
                    'max:10',
                    'regex:/^[A-Z][A-Z0-9]*$/',
                    Rule::unique('projects', 'key'),
                ],
            'description' => ['nullable', 'string', 'max:10000'],
            'member_ids' => ['present', 'array'],
            'member_ids.*' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'Use letters and numbers, starting with a letter.',
            'key.prohibited' => 'Project keys cannot be changed after creation.',
            'member_ids.*.exists' => 'One or more selected users no longer exist.',
            'member_ids.*.distinct' => 'A user can only be selected once.',
        ];
    }
}
