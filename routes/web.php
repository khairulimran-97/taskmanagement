<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NoteImageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    // Calendar - Main Inertia routes
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('/calendar/{calendar_event}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendar/{calendar_event}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    Route::patch('/calendar/{calendar_event}/dates', [CalendarController::class, 'updateDates'])->name('calendar.update-dates');

    // Calendar - JSON API endpoints (for AJAX calls only)
    Route::get('/api/calendar/events', [CalendarController::class, 'getEvents'])->name('calendar.api.events');
    Route::get('/api/calendar/events/{calendar_event}', [CalendarController::class, 'show'])->name('calendar.api.show');
    Route::get('/api/calendar/events-for-date', [CalendarController::class, 'getEventsForDate'])->name('calendar.api.events-for-date');
    Route::get('/api/calendar/upcoming', [CalendarController::class, 'getUpcomingEvents'])->name('calendar.api.upcoming');

    // Notes - Main Inertia routes (fully using Inertia now)
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Notes - Additional Inertia actions
    Route::post('/notes/create-empty', [NoteController::class, 'createEmpty'])->name('notes.create-empty');
    Route::patch('/notes/{note}/toggle-pin', [NoteController::class, 'togglePin'])->name('notes.toggle-pin');
    Route::patch('/notes/bulk-update', [NoteController::class, 'bulkUpdate'])->name('notes.bulk-update');

    // Notes - Minimal API endpoints (only for real-time features that can't use Inertia)
    Route::get('/api/notes/search', [NoteController::class, 'search'])->name('notes.api.search');

    // Note Images - Inertia routes
    Route::get('/notes/{note}/images', [NoteImageController::class, 'index'])->name('notes.images.index');
    Route::post('/notes/images', [NoteImageController::class, 'store'])->name('notes.images.store');
    Route::delete('/notes/images/{image}', [NoteImageController::class, 'destroy'])->name('notes.images.destroy');

    // Note Images - API endpoints for editor integration
    Route::post('/api/notes/images/upload', [NoteImageController::class, 'apiStore'])->name('notes.images.api.store');
    Route::get('/api/notes/{note}/images', [NoteImageController::class, 'apiIndex'])->name('notes.images.api.index');
    Route::delete('/api/notes/images/{image}', [NoteImageController::class, 'apiDestroy'])->name('notes.images.api.destroy');
    Route::get('/images/api', [NoteImageController::class, 'apiAllImages'])
        ->middleware('auth')
        ->name('images.api.index');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
