<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'normal',
        'lock_version' => 0,
    ];

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'assignee_id',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    protected function reference(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->project->key.'-'.$this->number,
        );
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', function (Builder $query) use ($user) {
            $query->visibleTo($user);
        });
    }
}
