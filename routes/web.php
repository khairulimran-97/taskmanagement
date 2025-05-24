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

    // Tasks
    Route::resource('tasks', TaskController::class)->except(['index', 'show', 'create', 'edit']);
    Route::get('/projects/{project}/tasks', [TaskController::class, 'getProjectTasks'])->name('projects.tasks');

    // Tags
    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
