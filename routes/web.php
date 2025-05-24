<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class);
    Route::post('/projects/reorder', [ProjectController::class, 'reorder'])->name('projects.reorder');

    // Tasks - Standard CRUD
    Route::resource('tasks', TaskController::class)->except(['index', 'show', 'create', 'edit']);

    // Task Quick Actions
    Route::patch('/tasks/{task}/status', [TaskController::class, 'quickStatusUpdate'])->name('tasks.quick-status');
    Route::post('/tasks/bulk-status', [TaskController::class, 'bulkStatusUpdate'])->name('tasks.bulk-status');
    Route::patch('/tasks/{task}/toggle-completion', [TaskController::class, 'toggleCompletion'])->name('tasks.toggle-completion');
    Route::post('/tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');

    // Task Data Endpoints
    Route::get('/projects/{project}/tasks', [TaskController::class, 'getProjectTasks'])->name('projects.tasks');
    Route::get('/projects/{project}/task-stats', [TaskController::class, 'getProjectTaskStats'])->name('projects.task-stats');
    Route::get('/tasks/overdue', [TaskController::class, 'getOverdueTasks'])->name('tasks.overdue');
    Route::get('/tasks/due-soon', [TaskController::class, 'getTasksDueSoon'])->name('tasks.due-soon');

    // Tags
    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
