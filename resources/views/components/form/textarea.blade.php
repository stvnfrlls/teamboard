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
        {{ $attributes->class([
            'block w-full rounded-lg border bg-white px-3 py-2.5 text-slate-900 shadow-sm focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600',
            'border-red-500' => $errors->has($name),
            'border-slate-300' => !$errors->has($name),
        ]) }}>{{ $text }}</textarea>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror
</div>
