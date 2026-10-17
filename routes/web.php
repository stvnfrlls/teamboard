<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskChecklistItemController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware(['auth:web', EnsureUserIsActive::class])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::resource('projects', ProjectController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::resource('users', UserController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);
    Route::scopeBindings()->group(function () {
        Route::resource('projects.tasks', TaskController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::patch(
            'projects/{project}/tasks/{task}/status',
            [TaskController::class, 'updateStatus']
        )->name('projects.tasks.status');
        Route::post(
            'projects/{project}/tasks/{task}/comments',
            [TaskCommentController::class, 'store']
        )->name('projects.tasks.comments.store');
        Route::post(
            'projects/{project}/tasks/{task}/checklist',
            [TaskChecklistItemController::class, 'store']
        )->name('projects.tasks.checklist.store');
        Route::patch(
            'projects/{project}/tasks/{task}/checklist/{checklistItem}',
            [TaskChecklistItemController::class, 'update']
        )->name('projects.tasks.checklist.update');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
