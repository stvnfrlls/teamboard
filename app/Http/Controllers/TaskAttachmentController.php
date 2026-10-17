<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\TaskManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function store(
        StoreTaskAttachmentRequest $request,
        Project $project,
        Task $task,
        TaskManager $tasks
    ): RedirectResponse {
        $tasks->addAttachment(
            $request->user('web'),
            $project,
            $task,
            $request->file('attachment')
        );

        return redirect()->route('projects.tasks.show', [$project, $task])
            ->withFragment('attachments')
            ->with('status', 'Attachment uploaded.');
    }

    public function download(
        Project $project,
        Task $task,
        TaskAttachment $attachment
    ): StreamedResponse {
        Gate::authorize('view', $task);

        $disk = Storage::disk(TaskAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }
}
