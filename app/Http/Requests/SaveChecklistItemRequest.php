<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Foundation\Http\FormRequest;

class SaveChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user('web')?->can('update', $task) ?? false);
    }

    public function rules(): array
    {
        if ($this->route('checklistItem') instanceof TaskChecklistItem) {
            return [
                'is_completed' => ['required', 'boolean'],
                'lock_version' => ['required', 'integer', 'min:0'],
                'label' => ['prohibited'],
            ];
        }

        return [
            'label' => ['required', 'string', 'max:255'],
            'is_completed' => ['prohibited'],
            'lock_version' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'lock_version.required' => 'Reload the checklist before saving this item.',
        ];
    }
}
