@php
    $extensions = \App\Models\TaskAttachment::EXTENSIONS;
    $accept = implode(',', array_map(fn($extension) => '.' . $extension, $extensions));
    $maxMegabytes = \App\Models\TaskAttachment::MAX_KILOBYTES / 1024;
    $uploadHelp = 'One file, up to ' . $maxMegabytes . ' MB. Allowed: ' . strtoupper(implode(', ', $extensions)) . '.';
@endphp

<section id="attachments" aria-labelledby="attachments-heading"
    class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
    <h2 id="attachments-heading" class="text-lg font-semibold">
        Attachments ({{ $attachments->total() }})
    </h2>

    @can('update', $task)
        <form method="POST" action="{{ route('projects.tasks.attachments.store', [$project, $task]) }}"
            enctype="multipart/form-data" class="mt-5 space-y-4">
            @csrf

            <x-form.file name="attachment" label="Choose a file" :accept="$accept" :help="$uploadHelp" required />

            <button type="submit"
                class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Upload attachment
            </button>
        </form>
    @endcan

    <ul class="mt-6 divide-y divide-slate-100">
        @forelse ($attachments as $attachment)
            <li class="py-4">
                <a href="{{ route('projects.tasks.attachments.download', [$project, $task, $attachment]) }}"
                    aria-label="Download {{ $attachment->original_name }}"
                    class="rounded break-all text-sm font-semibold text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $attachment->original_name }}
                </a>

                <p class="mt-2 break-words text-xs leading-5 text-slate-500">
                    {{ number_format($attachment->size / 1024, 1) }} KB
                    · Uploaded by {{ $attachment->uploader?->name ?? 'Unknown user' }}
                    ·
                    <time datetime="{{ $attachment->created_at->toIso8601String() }}">
                        {{ $attachment->created_at->format('M j, Y H:i') }} UTC
                    </time>
                </p>
            </li>
        @empty
            <li class="py-4 text-sm text-slate-500">
                No attachments yet.
            </li>
        @endforelse
    </ul>

    {{ $attachments->links() }}
</section>
