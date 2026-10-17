@props(['value'])

@php
    $tone = match ($value) {
        \App\Enums\TaskStatus::Todo, \App\Enums\TaskPriority::Low => 'bg-slate-100 text-slate-700',

        \App\Enums\TaskStatus::InProgress, \App\Enums\TaskPriority::Normal => 'bg-blue-50 text-blue-700',

        \App\Enums\TaskStatus::InReview, \App\Enums\TaskPriority::High => 'bg-amber-50 text-amber-800',

        \App\Enums\TaskStatus::Done => 'bg-emerald-50 text-emerald-700',

        \App\Enums\TaskPriority::Urgent => 'bg-red-50 text-red-700',
    };
@endphp

<span {{ $attributes->class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', $tone]) }}>
    {{ $value->label() }}
</span>
