@props(['name', 'label', 'type' => 'text', 'value' => null])

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700">
        {{ $label }}
    </label>

    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ is_scalar(old($name, $value)) ? old($name, $value) : '' }}" @endif
        @if ($errors->has($name)) aria-invalid="true"
            aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['ui-control']) }}>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror
</div>
