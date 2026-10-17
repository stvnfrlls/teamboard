<section id="comments" aria-labelledby="comments-heading"
    class="mt-6 scroll-mt-6 rounded-xl border border-slate-200 bg-white p-6">
    <h2 id="comments-heading" class="text-lg font-semibold">
        Comments ({{ $comments->total() }})
    </h2>

    @can('comment', $task)
        <form method="POST" action="{{ route('projects.tasks.comments.store', [$project, $task]) }}" class="mt-5 space-y-4">
            @csrf

            <x-form.textarea name="body" label="Add a comment" rows="4" maxlength="5000" required />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Plain text. Maximum 5,000 characters.
                </p>

                <button type="submit"
                    class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Add comment
                </button>
            </div>
        </form>
    @endcan

    <p class="mt-8 text-xs text-slate-500">
        Newest first. Times shown in UTC.
    </p>

    <ol class="mt-3 divide-y divide-slate-100">
        @forelse ($comments as $comment)
            <li id="comment-{{ $comment->id }}" class="py-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="break-words text-sm font-semibold">
                        {{ $comment->author->name }}
                    </p>

                    <time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-xs text-slate-500">
                        {{ $comment->created_at->format('M j, Y H:i') }}
                    </time>
                </div>

                <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ $comment->body }}
                </p>
            </li>
        @empty
            <li class="py-8 text-center text-sm text-slate-500">
                No comments yet.
            </li>
        @endforelse
    </ol>

    @if ($comments->hasPages())
        <div class="mt-5">
            {{ $comments->links() }}
        </div>
    @endif
</section>
