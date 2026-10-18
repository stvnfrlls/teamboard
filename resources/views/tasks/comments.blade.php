<section id="comments" aria-labelledby="comments-heading" class="ui-card mt-6 scroll-mt-24 p-6 sm:p-8">
    <h2 id="comments-heading" class="text-lg font-semibold text-slate-900">
        Comments ({{ $comments->total() }})
    </h2>

    <p class="mt-1 text-sm text-slate-500">
        Share updates and discuss this task with your team.
    </p>

    @can('comment', $task)
        <form method="POST" action="{{ route('projects.tasks.comments.store', [$project, $task]) }}"
            class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            @csrf

            <x-form.textarea name="body" label="Add a comment" rows="4" maxlength="5000" required />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Plain text. Maximum 5,000 characters.
                </p>

                <button type="submit" class="ui-button ui-button-primary">
                    Add comment
                </button>
            </div>
        </form>
    @endcan

    <p class="mt-6 text-xs text-slate-500">
        Newest first. Times shown in UTC.
    </p>

    <ol class="mt-3 space-y-4">
        @forelse ($comments as $comment)
            <li id="comment-{{ $comment->id }}" class="scroll-mt-24 rounded-xl border border-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-3">
                    <p class="min-w-0 break-words text-sm font-semibold text-slate-900">
                        {{ $comment->author->name }}
                    </p>

                    <time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-xs text-slate-500">
                        {{ $comment->created_at->format('M j, Y H:i') }}
                    </time>
                </div>

                <p class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ $comment->body }}
                </p>
            </li>
        @empty
            <li class="rounded-xl border border-dashed border-slate-200 px-5 py-10 text-center text-sm text-slate-500">
                No comments yet.
            </li>
        @endforelse
    </ol>

    @if ($comments->hasPages())
        <div class="mt-6">
            {{ $comments->links() }}
        </div>
    @endif
</section>
