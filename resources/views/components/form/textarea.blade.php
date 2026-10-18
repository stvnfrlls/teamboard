@props(['name', 'label', 'value' => null, 'rows' => 5])

@php
    $text = old($name, $value);
    $text = is_scalar($text) ? (string) $text : '';
@endphp

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700">
        {{ $label }}
    </label>

    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($errors->has($name)) aria-invalid="true"
            aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['ui-control']) }}>{{ $text }}</textarea>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror
</div>
