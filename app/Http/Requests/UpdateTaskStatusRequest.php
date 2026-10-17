<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

class UpdateTaskStatusRequest extends SaveTaskRequest
{
    public function rules(): array
    {
        return Arr::only(parent::rules(), [
            'status',
            'lock_version',
        ]);
    }
}
