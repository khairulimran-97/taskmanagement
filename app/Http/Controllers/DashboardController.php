<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Note;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::id();

        // Project Statistics
        $projectStats = [
            'total' => Project::where('user_id', $userId)->count(),
            'active' => Project::where('user_id', $userId)->where('status', 'active')->count(),
            'completed' => Project::where('user_id', $userId)->where('status', 'completed')->count(),
            'paused' => Project::where('user_id', $userId)->where('status', 'paused')->count(),
            'archived' => Project::where('user_id', $userId)->where('status', 'archived')->count(),
        ];

        // Task Statistics
        $taskStats = [
            'total' => Task::where('user_id', $userId)->count(),
            'todo' => Task::where('user_id', $userId)->where('status', 'todo')->count(),
            'in_progress' => Task::where('user_id', $userId)->where('status', 'in_progress')->count(),
            'completed' => Task::where('user_id', $userId)->where('status', 'completed')->count(),
            'cancelled' => Task::where('user_id', $userId)->where('status', 'cancelled')->count(),
            'overdue' => Task::where('user_id', $userId)
                ->where('due_date', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
            'due_soon' => Task::where('user_id', $userId)
                ->whereBetween('due_date', [now(), now()->addDays(7)])
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
        ];

        // Note Statistics
        $noteStats = [
            'total' => Note::where('user_id', $userId)->count(),
            'pinned' => Note::where('user_id', $userId)->where('is_pinned', true)->count(),
            'recent' => Note::where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        // Calendar Statistics
        $calendarStats = [
            'total' => CalendarEvent::where('user_id', $userId)->count(),
            'today' => CalendarEvent::where('user_id', $userId)
                ->whereDate('start_date', today())
                ->count(),
            'this_week' => CalendarEvent::where('user_id', $userId)
                ->whereBetween('start_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
        ];

        // Recent Projects (latest 5)
        $recentProjects = Project::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'priority' => $project->priority,
                    'color' => $project->color,
                    'completion_percentage' => $project->completion_percentage,
                    'created_at' => $project->created_at,
                    'due_date' => $project->due_date?->toISOString(),
                ];
            });

        // Recent Tasks (latest 10)
        $recentTasks = Task::where('user_id', $userId)
            ->with(['project:id,name,color', 'tags:id,name,color'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date?->toISOString(),
                    'created_at' => $task->created_at,
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                        'color' => $task->project->color,
                    ] : null,
                    'tags' => $task->tags?->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'color' => $tag->color,
                        ];
                    }),
                ];
            });

        // Latest Notes (latest 5)
        $latestNotes = Note::where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'content_preview' => $note->content_preview,
                    'tags_array' => $note->tags_array,
                    'is_pinned' => $note->is_pinned,
                    'word_count' => $note->word_count,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
                    'last_accessed_at' => $note->last_accessed_at?->toISOString(),
                ];
            });

        // Upcoming Calendar Events (next 5)
        $upcomingEvents = CalendarEvent::where('user_id', $userId)
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date->toISOString(),
                    'end_date' => $event->end_date?->toISOString(),
                    'color' => $event->color,
                    'all_day' => $event->all_day,
                    'formatted_start_date' => $event->formatted_start_date,
                    'formatted_end_date' => $event->formatted_end_date,
                    'days_until_event' => now()->diffInDays($event->start_date, false),
                ];
            });

        // Overdue Tasks
        $overdueTasks = Task::where('user_id', $userId)
            ->with(['project:id,name,color'])
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date?->toISOString(),
                    'days_overdue' => now()->diffInDays($task->due_date, false),
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                        'color' => $task->project->color,
                    ] : null,
                ];
            });

        // Tasks Due Soon (next 7 days)
        $tasksDueSoon = Task::where('user_id', $userId)
            ->with(['project:id,name,color'])
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date?->toISOString(),
                    'days_until_due' => now()->diffInDays($task->due_date, false),
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                        'color' => $task->project->color,
                    ] : null,
                ];
            });

        // Priority Distribution for Projects
        $projectPriorityDistribution = [
            'high' => Project::where('user_id', $userId)->where('priority', 'high')->count(),
            'medium' => Project::where('user_id', $userId)->where('priority', 'medium')->count(),
            'low' => Project::where('user_id', $userId)->where('priority', 'low')->count(),
        ];

        // Task Priority Distribution
        $taskPriorityDistribution = [
            'urgent' => Task::where('user_id', $userId)->where('priority', 'urgent')->count(),
            'high' => Task::where('user_id', $userId)->where('priority', 'high')->count(),
            'medium' => Task::where('user_id', $userId)->where('priority', 'medium')->count(),
            'low' => Task::where('user_id', $userId)->where('priority', 'low')->count(),
        ];

        // Completion Rate
        $completionRates = [
            'projects' => $projectStats['total'] > 0
                ? round(($projectStats['completed'] / $projectStats['total']) * 100, 1)
                : 0,
            'tasks' => $taskStats['total'] > 0
                ? round(($taskStats['completed'] / $taskStats['total']) * 100, 1)
                : 0,
        ];

        // Calculate total notifications
        $notifications = [
            'total' => $taskStats['overdue'] + $taskStats['due_soon'] + $calendarStats['today'],
            'overdue_tasks' => $taskStats['overdue'],
            'due_soon_tasks' => $taskStats['due_soon'],
            'today_events' => $calendarStats['today'],
        ];

        return Inertia::render('Dashboard', [
            'projectStats' => $projectStats,
            'taskStats' => $taskStats,
            'noteStats' => $noteStats,
            'calendarStats' => $calendarStats,
            'recentProjects' => $recentProjects,
            'recentTasks' => $recentTasks,
            'latestNotes' => $latestNotes,
            'upcomingEvents' => $upcomingEvents,
            'overdueTasks' => $overdueTasks,
            'tasksDueSoon' => $tasksDueSoon,
            'projectPriorityDistribution' => $projectPriorityDistribution,
            'taskPriorityDistribution' => $taskPriorityDistribution,
            'completionRates' => $completionRates,
            'notifications' => $notifications,
        ]);
    }
}
