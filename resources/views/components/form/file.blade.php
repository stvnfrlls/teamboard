@props(['name', 'label', 'help' => null])

<div>
    <label for="{{ $name }}" class="mb-2 block text-sm font-medium text-slate-700">
        {{ $label }}
    </label>

    @if ($help)
        <p id="{{ $name }}-help" class="mb-3 text-sm text-slate-500">
            {{ $help }}
        </p>
    @endif

    <input id="{{ $name }}" name="{{ $name }}" type="file"
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($help || $errors->has($name)) aria-describedby="{{ trim(($help ? $name . '-help ' : '') . ($errors->has($name) ? $name . '-error' : '')) }}" @endif
        {{ $attributes->class([
            'block w-full min-w-0 rounded-lg border bg-white p-3 text-sm text-slate-700 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600',
            'border-red-500' => $errors->has($name),
            'border-slate-300' => !$errors->has($name),
        ]) }}>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="mt-2 text-sm text-red-700">
            {{ $message }}
        </p>
    @enderror
</div>
