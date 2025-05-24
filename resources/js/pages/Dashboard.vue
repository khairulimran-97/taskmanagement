<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    FolderOpen,
    CheckCircle2,
    Clock,
    AlertTriangle,
    TrendingUp,
    Activity,
    Plus,
    ArrowRight,
    ExternalLink
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
    recentProjects: any[];
    recentTasks: any[];
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
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dashboard" />

        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- Welcome Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Dashboard</h1>
                <p class="text-gray-600 dark:text-gray-400">Welcome back! Here's an overview of your projects and tasks.</p>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Projects -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Projects</CardTitle>
                        <FolderOpen class="h-4 w-4 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ projectStats.total }}</div>
                        <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <span class="text-green-600 dark:text-green-400">{{ projectStats.active }} active</span>
                            <span class="mx-1">•</span>
                            <span>{{ projectStats.completed }} completed</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Total Tasks -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Tasks</CardTitle>
                        <CheckCircle2 class="h-4 w-4 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ taskStats.total }}</div>
                        <div class="flex items-center text-xs text-gray-600 dark:text-gray-400 mt-1">
                            <span class="text-blue-600 dark:text-blue-400">{{ taskStats.in_progress }} in progress</span>
                            <span class="mx-1">•</span>
                            <span>{{ taskStats.completed }} done</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Overdue Tasks -->
                <Card class="hover:shadow-md transition-shadow" :class="taskStats.overdue > 0 ? 'border-red-200 dark:border-red-800' : ''">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Overdue Tasks</CardTitle>
                        <AlertTriangle class="h-4 w-4" :class="taskStats.overdue > 0 ? 'text-red-600' : 'text-gray-400'" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold" :class="taskStats.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'">
                            {{ taskStats.overdue }}
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            {{ taskStats.due_soon }} due this week
                        </div>
                    </CardContent>
                </Card>

                <!-- Completion Rate -->
                <Card class="hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Completion Rate</CardTitle>
                        <TrendingUp class="h-4 w-4 text-purple-600" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ completionRates.tasks }}%</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            Projects: {{ completionRates.projects }}%
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Progress Bars -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Project Progress -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Project Progress</CardTitle>
                        <CardDescription>Overview of all your projects</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Overall Completion</span>
                                <span class="text-sm font-medium">{{ completionRates.projects }}%</span>
                            </div>
                            <Progress :value="completionRates.projects" class="h-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                <span>Active: {{ projectStats.active }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span>Completed: {{ projectStats.completed }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-orange-500 rounded-full mr-2"></div>
                                <span>Paused: {{ projectStats.paused }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-gray-500 rounded-full mr-2"></div>
                                <span>Archived: {{ projectStats.archived }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Task Progress -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Task Progress</CardTitle>
                        <CardDescription>Breakdown of task statuses</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Overall Completion</span>
                                <span class="text-sm font-medium">{{ completionRates.tasks }}%</span>
                            </div>
                            <Progress :value="completionRates.tasks" class="h-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-slate-500 rounded-full mr-2"></div>
                                <span>To Do: {{ taskStats.todo }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                <span>In Progress: {{ taskStats.in_progress }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span>Completed: {{ taskStats.completed }}</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                <span>Cancelled: {{ taskStats.cancelled }}</span>
                            </div>
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

            <!-- Main Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Projects -->
                <Card class="lg:col-span-1">
                    <CardHeader>
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
                    <CardContent>
                        <div v-if="recentProjects.length > 0" class="space-y-4">
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
                        <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400">
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
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Recent Tasks</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('projects.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    View all
                                    <ArrowRight class="h-3 w-3 ml-1" />
                                </Link>
                            </Button>
                        </div>
                        <CardDescription>Your latest task activity</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentTasks.length > 0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[40%]">Task</TableHead>
                                        <TableHead class="w-[20%]">Project</TableHead>
                                        <TableHead class="w-[15%]">Status</TableHead>
                                        <TableHead class="w-[15%]">Priority</TableHead>
                                        <TableHead class="w-[10%] text-right">Due</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="task in recentTasks" :key="task.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <TableCell class="font-medium">
                                            <div class="truncate">{{ task.title }}</div>
                                            <div v-if="task.tags && task.tags.length > 0" class="flex flex-wrap gap-1 mt-1">
                                                <Badge v-for="tag in task.tags.slice(0, 2)" :key="tag.id" variant="outline" class="text-xs px-1 py-0" :style="`border-color: ${tag.color}; color: ${tag.color}`">
                                                    {{ tag.name }}
                                                </Badge>
                                                <Badge v-if="task.tags.length > 2" variant="outline" class="text-xs px-1 py-0">
                                                    +{{ task.tags.length - 2 }}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div v-if="task.project" class="flex items-center space-x-2">
                                                <div class="w-2 h-2 rounded-full" :style="`background-color: ${task.project.color}`"></div>
                                                <span class="text-xs text-gray-600 dark:text-gray-400 truncate">{{ task.project.name }}</span>
                                            </div>
                                            <span v-else class="text-xs text-gray-400">-</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :class="getStatusClass(task.status, 'task')" class="text-xs px-1.5 py-0.5">
                                                {{ task.status.replace('_', ' ') }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :class="getPriorityClass(task.priority)" variant="outline" class="text-xs px-1.5 py-0.5">
                                                {{ task.priority }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-right text-xs text-gray-500 dark:text-gray-400">
                                            {{ formatDate(task.due_date) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400">
                            <Activity class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No recent tasks</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
