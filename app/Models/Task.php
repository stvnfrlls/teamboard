<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
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

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('tasks.assignee_id', $user->getKey());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('tasks.status', '!=', TaskStatus::Done->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->where('tasks.due_date', '<', now()->toDateString());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->open()
            ->where('tasks.due_date', now()->toDateString());
    }

    public function scopeByDueDate(Builder $query): Builder
    {
        return $query->orderByRaw('tasks.due_date IS NULL')
            ->orderBy('tasks.due_date')
            ->orderByDesc('tasks.id');
    }

    public function scopeMatchingFilters(Builder $query, array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $reference = [];

            preg_match('/^([A-Z][A-Z0-9]{1,9})-([0-9]+)$/i', $search, $reference);

            $query->where(function (Builder $query) use ($search, $reference) {
                $query->where('tasks.title', 'like', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $query->orWhere('tasks.number', $search);
                } elseif ($reference !== []) {
                    $query->orWhere(function (Builder $query) use ($reference) {
                        $query->where('tasks.number', $reference[2])
                            ->whereHas('project', function (Builder $query) use ($reference) {
                                $query->where('key', Str::upper($reference[1]));
                            });
                    });
                }
            });
        }

        $query->when($filters['status'] ?? null, function (Builder $query, $status) {
            $query->where('tasks.status', $status);
        });

        $query->when($filters['priority'] ?? null, function (Builder $query, $priority) {
            $query->where('tasks.priority', $priority);
        });

        if (($filters['due'] ?? null) === 'overdue') {
            $query->overdue();
        } elseif (($filters['due'] ?? null) === 'today') {
            $query->dueToday();
        }

        return $query;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', function (Builder $query) use ($user) {
            $query->visibleTo($user);
        });
    }
}
