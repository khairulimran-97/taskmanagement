<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
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

        // Weekly Activity Data (last 7 days)
        $weeklyActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $tasksCreated = Task::where('user_id', $userId)
                ->whereDate('created_at', $date)
                ->count();
            $tasksCompleted = Task::where('user_id', $userId)
                ->whereDate('completed_at', $date)
                ->count();

            $weeklyActivity[] = [
                'date' => $date->format('M j'),
                'created' => $tasksCreated,
                'completed' => $tasksCompleted,
            ];
        }

        // Monthly Progress (last 6 months)
        $monthlyProgress = [];
        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = now()->subMonths($i)->startOfMonth();
            $endOfMonth = now()->subMonths($i)->endOfMonth();

            $monthlyTasks = Task::where('user_id', $userId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();
            $monthlyCompleted = Task::where('user_id', $userId)
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->count();

            $monthlyProgress[] = [
                'month' => $startOfMonth->format('M Y'),
                'total' => $monthlyTasks,
                'completed' => $monthlyCompleted,
                'completion_rate' => $monthlyTasks > 0 ? round(($monthlyCompleted / $monthlyTasks) * 100, 1) : 0,
            ];
        }

        // Project Status Chart Data
        $projectStatusChart = [
            'labels' => ['Active', 'Completed', 'Paused', 'Archived'],
            'data' => [
                $projectStats['active'],
                $projectStats['completed'],
                $projectStats['paused'],
                $projectStats['archived']
            ],
            'colors' => ['#3B82F6', '#10B981', '#F59E0B', '#6B7280']
        ];

        // Task Status Chart Data
        $taskStatusChart = [
            'labels' => ['To Do', 'In Progress', 'Completed', 'Cancelled'],
            'data' => [
                $taskStats['todo'],
                $taskStats['in_progress'],
                $taskStats['completed'],
                $taskStats['cancelled']
            ],
            'colors' => ['#64748B', '#3B82F6', '#10B981', '#EF4444']
        ];

        // Priority Distribution Chart Data
        $priorityChart = [
            'labels' => ['Urgent', 'High', 'Medium', 'Low'],
            'data' => [
                $taskPriorityDistribution['urgent'],
                $taskPriorityDistribution['high'],
                $taskPriorityDistribution['medium'],
                $taskPriorityDistribution['low']
            ],
            'colors' => ['#DC2626', '#EA580C', '#D97706', '#65A30D']
        ];

        // Top Projects by Task Count
        $topProjects = Project::where('user_id', $userId)
            ->withCount('tasks')
            ->orderBy('tasks_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($project) {
                return [
                    'name' => $project->name,
                    'task_count' => $project->tasks_count,
                    'color' => $project->color,
                ];
            });

        return Inertia::render('Dashboard', [
            'projectStats' => $projectStats,
            'taskStats' => $taskStats,
            'recentProjects' => $recentProjects,
            'recentTasks' => $recentTasks,
            'overdueTasks' => $overdueTasks,
            'tasksDueSoon' => $tasksDueSoon,
            'projectPriorityDistribution' => $projectPriorityDistribution,
            'taskPriorityDistribution' => $taskPriorityDistribution,
            'completionRates' => $completionRates,
            'weeklyActivity' => $weeklyActivity,
            'monthlyProgress' => $monthlyProgress,
            'projectStatusChart' => $projectStatusChart,
            'taskStatusChart' => $taskStatusChart,
            'priorityChart' => $priorityChart,
            'topProjects' => $topProjects,
        ]);
    }
}
