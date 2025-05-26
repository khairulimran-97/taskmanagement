<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    FolderOpen,
    CheckCircle2,
    Clock,
    AlertTriangle,
    TrendingUp,
    Calendar,
    Target,
    Activity,
    Users,
    Plus,
    ArrowRight,
    ExternalLink,
    FileText,
    Pin,
    CalendarDays,
    Eye,
    BookOpen,
    Zap
} from 'lucide-vue-next';

interface Props {
    projectStats: {
        total: number;
        active: number;
        completed: number;
        paused: number;
        archived: number;
    };
    taskStats: {
        total: number;
        todo: number;
        in_progress: number;
        completed: number;
        cancelled: number;
        overdue: number;
        due_soon: number;
    };
    noteStats: {
        total: number;
        pinned: number;
        recent: number;
    };
    calendarStats: {
        total: number;
        today: number;
        this_week: number;
    };
    recentProjects: any[];
    recentTasks: any[];
    latestNotes: any[];
    upcomingEvents: any[];
    overdueTasks: any[];
    tasksDueSoon: any[];
    projectPriorityDistribution: {
        high: number;
        medium: number;
        low: number;
    };
    taskPriorityDistribution: {
        urgent: number;
        high: number;
        medium: number;
        low: number;
    };
    completionRates: {
        projects: number;
        tasks: number;
    };
    notifications: {
        total: number;
        overdue_tasks: number;
        due_soon_tasks: number;
        today_events: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItemType[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Helper functions
const formatDate = (dateString: string | null): string => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};

const getRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));

    if (diffInHours < 1) return 'Just now';
    if (diffInHours < 24) return `${diffInHours}h ago`;

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d ago`;

    const diffInWeeks = Math.floor(diffInDays / 7);
    return `${diffInWeeks}w ago`;
};

const getStatusClass = (status: string, type: 'project' | 'task'): string => {
    if (type === 'project') {
        switch (status) {
            case 'active': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'paused': return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
            case 'archived': return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
            default: return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
        }
    } else {
        switch (status) {
            case 'todo': return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200';
            case 'in_progress': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'cancelled': return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            default: return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
        }
    }
};

const getPriorityClass = (priority: string): string => {
    switch (priority) {
        case 'urgent': return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900 dark:text-red-200 dark:border-red-800';
        case 'high': return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900 dark:text-red-200 dark:border-red-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:border-yellow-800';
        case 'low': return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900 dark:text-green-200 dark:border-green-800';
        default: return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600';
    }
};

const formatEventDate = (dateString: string, allDay: boolean = false): string => {
    const date = new Date(dateString);
    if (allDay) {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });
    }
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :notifications="notifications">
        <Head title="Dashboard" />

        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- Welcome Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Dashboard</h1>
                <p class="text-gray-600 dark:text-gray-400">Welcome back! Here's an overview of your projects, tasks, notes, and calendar.</p>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Projects -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Projects</CardTitle>
                        <FolderOpen class="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ projectStats.total }}</div>
                        <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <span class="text-green-600 dark:text-green-400">{{ projectStats.active }} active</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Total Tasks -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Tasks</CardTitle>
                        <CheckCircle2 class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ taskStats.total }}</div>
                        <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <span class="text-blue-600 dark:text-blue-400">{{ taskStats.in_progress }} active</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Total Notes -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Notes</CardTitle>
                        <FileText class="h-4 w-4 text-purple-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ noteStats.total }}</div>
                        <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <span class="text-purple-600 dark:text-purple-400">{{ noteStats.pinned }} pinned</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Events -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Events</CardTitle>
                        <CalendarDays class="h-4 w-4 text-indigo-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ calendarStats.total }}</div>
                        <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <span class="text-indigo-600 dark:text-indigo-400">{{ calendarStats.today }} today</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Alert Sections -->
            <div v-if="overdueTasks.length > 0 || tasksDueSoon.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Overdue Tasks -->
                <Card v-if="overdueTasks.length > 0" class="border-red-200 dark:border-red-800">
                    <CardHeader>
                        <CardTitle class="text-base text-red-700 dark:text-red-300 flex items-center">
                            <AlertTriangle class="h-4 w-4 mr-2" />
                            Overdue Tasks
                        </CardTitle>
                        <CardDescription>Tasks that need immediate attention</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="task in overdueTasks" :key="task.id" class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-950 rounded-lg">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2">
                                        <div v-if="task.project" class="w-2 h-2 rounded-full" :style="`background-color: ${task.project.color}`"></div>
                                        <span class="font-medium text-sm truncate">{{ task.title }}</span>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        <span v-if="task.project">{{ task.project.name }}</span>
                                        <span class="mx-1" v-if="task.project">•</span>
                                        <span class="text-red-600 dark:text-red-400">{{ Math.abs(task.days_overdue) }} days overdue</span>
                                    </div>
                                </div>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link :href="route('projects.show', task.project?.id)" class="text-red-600 dark:text-red-400">
                                        <ExternalLink class="h-3 w-3" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Tasks Due Soon -->
                <Card v-if="tasksDueSoon.length > 0" class="border-orange-200 dark:border-orange-800">
                    <CardHeader>
                        <CardTitle class="text-base text-orange-700 dark:text-orange-300 flex items-center">
                            <Clock class="h-4 w-4 mr-2" />
                            Due This Week
                        </CardTitle>
                        <CardDescription>Tasks approaching their deadline</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="task in tasksDueSoon" :key="task.id" class="flex items-center justify-between p-3 bg-orange-50 dark:bg-orange-950 rounded-lg">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2">
                                        <div v-if="task.project" class="w-2 h-2 rounded-full" :style="`background-color: ${task.project.color}`"></div>
                                        <span class="font-medium text-sm truncate">{{ task.title }}</span>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        <span v-if="task.project">{{ task.project.name }}</span>
                                        <span class="mx-1" v-if="task.project">•</span>
                                        <span class="text-orange-600 dark:text-orange-400">Due in {{ task.days_until_due }} day{{ task.days_until_due !== 1 ? 's' : '' }}</span>
                                    </div>
                                </div>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link :href="route('projects.show', task.project?.id)" class="text-orange-600 dark:text-orange-400">
                                        <ExternalLink class="h-3 w-3" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Recent Projects -->
                <Card class="flex flex-col h-full">
                    <CardHeader class="flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Recent Projects</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('projects.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    View all
                                    <ArrowRight class="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </div>
                        <CardDescription>Your latest projects</CardDescription>
                    </CardHeader>
                    <CardContent class="flex-1 flex flex-col">
                        <div v-if="recentProjects.length > 0" class="space-y-4 flex-1">
                            <div v-for="project in recentProjects" :key="project.id" class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full" :style="`background-color: ${project.color}`"></div>
                                <div class="flex-1 min-w-0">
                                    <Link :href="route('projects.show', project.id)" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline truncate block">
                                        {{ project.name }}
                                    </Link>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <Badge :class="getStatusClass(project.status, 'project')" class="text-xs px-1.5 py-0.5">
                                            {{ project.status }}
                                        </Badge>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ getRelativeTime(project.created_at) }}</span>
                                    </div>
                                </div>
                                <div v-if="typeof project.completion_percentage === 'number'" class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ project.completion_percentage }}%
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 flex-1 flex flex-col justify-center">
                            <FolderOpen class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No projects yet</p>
                            <Button variant="outline" size="sm" asChild class="mt-2">
                                <Link :href="route('projects.index')">
                                    <Plus class="h-3 w-3 mr-1" />
                                    Create Project
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Tasks -->
                <Card class="flex flex-col h-full">
                    <CardHeader class="flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Recent Tasks</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('projects.index')" class="text-sm text-green-600 dark:text-green-400 hover:underline">
                                    View all
                                    <ArrowRight class="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </div>
                        <CardDescription>Latest task activity</CardDescription>
                    </CardHeader>
                    <CardContent class="flex-1 flex flex-col">
                        <div v-if="recentTasks.length > 0" class="space-y-4 flex-1">
                            <div v-for="task in recentTasks.slice(0, 5)" :key="task.id" class="space-y-2">
                                <div class="flex items-start space-x-2">
                                    <div v-if="task.project" class="w-2 h-2 rounded-full mt-2" :style="`background-color: ${task.project.color}`"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium truncate">{{ task.title }}</span>
                                            <Badge :class="getStatusClass(task.status, 'task')" class="text-xs px-1.5 py-0.5 ml-2">
                                                {{ task.status.replace('_', ' ') }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center space-x-2 mt-1">
                                            <span v-if="task.project" class="text-xs text-gray-500 dark:text-gray-400">{{ task.project.name }}</span>
                                            <Badge v-if="task.priority" :class="getPriorityClass(task.priority)" variant="outline" class="text-xs px-1 py-0">
                                                {{ task.priority }}
                                            </Badge>
                                        </div>
                                        <div v-if="task.tags && task.tags.length > 0" class="flex flex-wrap gap-1 mt-1">
                                            <Badge v-for="tag in task.tags.slice(0, 2)" :key="tag.id" variant="outline" class="text-xs px-1 py-0" :style="`border-color: ${tag.color}; color: ${tag.color}`">
                                                {{ tag.name }}
                                            </Badge>
                                            <Badge v-if="task.tags.length > 2" variant="outline" class="text-xs px-1 py-0">
                                                +{{ task.tags.length - 2 }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 flex-1 flex flex-col justify-center">
                            <Activity class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No recent tasks</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Latest Notes -->
                <Card class="flex flex-col h-full">
                    <CardHeader class="flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Latest Notes</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('notes.index')" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">
                                    View all
                                    <ArrowRight class="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </div>
                        <CardDescription>Recent note activity</CardDescription>
                    </CardHeader>
                    <CardContent class="flex-1 flex flex-col">
                        <div v-if="latestNotes.length > 0" class="space-y-4 flex-1">
                            <div v-for="note in latestNotes" :key="note.id" class="space-y-2">
                                <div class="flex items-start space-x-2">
                                    <Pin v-if="note.is_pinned" class="h-3 w-3 text-purple-600 dark:text-purple-400 mt-1 flex-shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <Link :href="route('notes.show', note.id)" class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline block truncate">
                                            {{ note.title }}
                                        </Link>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                            {{ note.content_preview }}
                                        </p>
                                        <div class="flex items-center space-x-2 mt-1">
                                            <span class="text-xs text-gray-400">{{ note.word_count }} words</span>
                                            <span class="text-xs text-gray-400">•</span>
                                            <span class="text-xs text-gray-400">{{ getRelativeTime(note.updated_at) }}</span>
                                        </div>
                                        <div v-if="note.tags_array.length > 0" class="flex flex-wrap gap-1 mt-1">
                                            <Badge v-for="tag in note.tags_array.slice(0, 2)" :key="tag" variant="outline" class="text-xs px-1 py-0">
                                                {{ tag }}
                                            </Badge>
                                            <Badge v-if="note.tags_array.length > 2" variant="outline" class="text-xs px-1 py-0">
                                                +{{ note.tags_array.length - 2 }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 flex-1 flex flex-col justify-center">
                            <FileText class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No notes yet</p>
                            <Button variant="outline" size="sm" asChild class="mt-2">
                                <Link :href="route('notes.index')">
                                    <Plus class="h-3 w-3 mr-1" />
                                    Create Note
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Upcoming Events -->
                <Card class="flex flex-col h-full">
                    <CardHeader class="flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Upcoming Events</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('calendar.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                    View all
                                    <ArrowRight class="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </div>
                        <CardDescription>Your upcoming calendar events</CardDescription>
                    </CardHeader>
                    <CardContent class="flex-1 flex flex-col">
                        <div v-if="upcomingEvents.length > 0" class="space-y-4 flex-1">
                            <div v-for="event in upcomingEvents" :key="event.id" class="flex items-start space-x-3">
                                <div class="w-3 h-3 rounded-full mt-1 flex-shrink-0" :style="`background-color: ${event.color}`"></div>
                                <div class="flex-1 min-w-0">
                                    <Link :href="route('calendar.index')" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline block truncate">
                                        {{ event.title }}
                                    </Link>
                                    <p v-if="event.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">
                                        {{ event.description }}
                                    </p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="text-xs text-gray-400">{{ formatEventDate(event.start_date, event.all_day) }}</span>
                                        <span v-if="event.days_until_event >= 0" class="text-xs text-indigo-600 dark:text-indigo-400">
                                            • {{ event.days_until_event === 0 ? 'Today' : `In ${event.days_until_event} day${event.days_until_event > 1 ? 's' : ''}` }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 flex-1 flex flex-col justify-center">
                            <CalendarDays class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No upcoming events</p>
                            <Button variant="outline" size="sm" asChild class="mt-2">
                                <Link :href="route('calendar.index')">
                                    <Plus class="h-3 w-3 mr-1" />
                                    Add Event
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
