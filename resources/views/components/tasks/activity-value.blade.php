@props(['field', 'value', 'assigneeNames' => []])

@php
    $display = match ($field) {
        'assignee_id' => $value === null ? 'Unassigned' : $assigneeNames[$value] ?? 'User #' . $value,
        'status' => \App\Enums\TaskStatus::tryFrom((string) $value)?->label() ?? (string) $value,
        'priority' => \App\Enums\TaskPriority::tryFrom((string) $value)?->label() ?? (string) $value,
        default => $value === null ? 'Not set' : ($value === '' ? 'Empty' : (string) $value),
    };
@endphp

<span class="whitespace-pre-wrap break-words">{{ $display }}</span>
