@if (session('status'))
    <div role="status"
        {{ $attributes->class([
            'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-800',
        ]) }}>
        {{ session('status') }}
    </div>
@endif
