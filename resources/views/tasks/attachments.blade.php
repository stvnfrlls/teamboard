@php
    $extensions = \App\Models\TaskAttachment::EXTENSIONS;
    $accept = implode(',', array_map(fn($extension) => '.' . $extension, $extensions));
    $maxMegabytes = \App\Models\TaskAttachment::MAX_KILOBYTES / 1024;
    $uploadHelp = 'One file, up to ' . $maxMegabytes . ' MB. Allowed: ' . strtoupper(implode(', ', $extensions)) . '.';
@endphp

<section id="attachments" aria-labelledby="attachments-heading" class="ui-card mt-6 scroll-mt-24 p-6 sm:p-8">
    <h2 id="attachments-heading" class="text-lg font-semibold text-slate-900">
        Attachments ({{ $attachments->total() }})
    </h2>

    <p class="mt-1 text-sm text-slate-500">
        Files shared with this task.
    </p>

    @can('update', $task)
        <form method="POST" action="{{ route('projects.tasks.attachments.store', [$project, $task]) }}"
            enctype="multipart/form-data" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            @csrf

            <x-form.file name="attachment" label="Choose a file" :accept="$accept" :help="$uploadHelp" required />

            <button type="submit" class="ui-button ui-button-primary">
                Upload attachment
            </button>
        </form>
    @endcan

    <ul class="mt-6 space-y-3">
        @forelse ($attachments as $attachment)
            <li class="min-w-0 rounded-xl border border-slate-200 p-4 sm:p-5">
                <a href="{{ route('projects.tasks.attachments.download', [$project, $task, $attachment]) }}"
                    aria-label="Download {{ $attachment->original_name }}" class="ui-link break-all text-sm">
                    {{ $attachment->original_name }}
                </a>

                <p class="mt-2 break-words text-xs leading-6 text-slate-500">
                    {{ number_format($attachment->size / 1024, 1) }} KB
                    · Uploaded by {{ $attachment->uploader?->name ?? 'Unknown user' }}
                    ·
                    <time datetime="{{ $attachment->created_at->toIso8601String() }}">
                        {{ $attachment->created_at->format('M j, Y H:i') }} UTC
                    </time>
                </p>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-slate-200 px-5 py-10 text-center text-sm text-slate-500">
                No attachments yet.
            </li>
        @endforelse
    </ul>

    @if ($attachments->hasPages())
        <div class="mt-6">
            {{ $attachments->links() }}
        </div>
    @endif
</section>
