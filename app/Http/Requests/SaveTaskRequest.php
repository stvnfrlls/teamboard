<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $task = $this->route('task');
        $actor = $this->user('web');

        if (! $project instanceof Project || ! $actor) {
            return false;
        }

        return $task instanceof Task
            ? $actor->can('update', $task)
            : $actor->can('create', [Task::class, $project]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assignee_id' => [
                'bail',
                'present',
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'due_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'lock_version' => $this->route('task')
                ? ['required', 'integer', 'min:0']
                : ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignee_id.exists' => 'The selected user no longer exists.',
            'lock_version.required' => 'Reload this task before saving it.',
        ];
    }
}
