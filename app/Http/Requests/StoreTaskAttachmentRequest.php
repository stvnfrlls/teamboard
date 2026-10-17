<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user('web')?->can('update', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'attachment' => [
                'required',
                File::types(TaskAttachment::EXTENSIONS)
                    ->max(TaskAttachment::MAX_KILOBYTES),
                'extensions:'.implode(',', TaskAttachment::EXTENSIONS),
            ],
        ];
    }
}
