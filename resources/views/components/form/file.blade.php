@props(['name', 'label', 'help' => null])

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700">
        {{ $label }}
    </label>

    @if ($help)
        <p id="{{ $name }}-help" class="mb-3 text-xs leading-6 text-slate-500">
            {{ $help }}
        </p>
    @endif

    <input id="{{ $name }}" name="{{ $name }}" type="file"
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($help || $errors->has($name)) aria-describedby="{{ trim(($help ? $name . '-help ' : '') . ($errors->has($name) ? $name . '-error' : '')) }}" @endif
        {{ $attributes->class([
            'ui-control min-w-0 file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100',
        ]) }}>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror
</div>
