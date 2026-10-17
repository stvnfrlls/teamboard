<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAttachment extends Model
{
    public const DISK = 'task_attachments';

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'csv'];

    public const MAX_KILOBYTES = 10240;

    protected $fillable = [
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected $hidden = [
        'path',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
