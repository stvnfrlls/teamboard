@props(['name', 'label', 'options', 'value' => null])

@php
    $selected = $attributes->get('disabled') ? $value : old($name, $value);
    $selected = is_scalar($selected) ? (string) $selected : '';
@endphp

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700">
        {{ $label }}
    </label>

    <select id="{{ $name }}" name="{{ $name }}"
        @if ($errors->has($name)) aria-invalid="true"
            aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['ui-control']) }}>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror
</div>
