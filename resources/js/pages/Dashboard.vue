<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import RingStat from '@/components/RingStat.vue';
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

const page = usePage();

const firstName = computed(() => {
    const name = (page.props.auth as any)?.user?.name ?? '';
    return name.split(' ')[0] || 'there';
});

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
});

const today = computed(() =>
    new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }),
);

const createNote = () => router.post(route('notes.create-empty'));

// Task status breakdown for the stacked bar + legend
const taskSegments = computed(() => [
    { key: 'completed', label: 'Completed', count: props.taskStats.completed, class: 'bg-primary' },
    { key: 'in_progress', label: 'In progress', count: props.taskStats.in_progress, class: 'bg-chart-2' },
    { key: 'todo', label: 'To do', count: props.taskStats.todo, class: 'bg-muted-foreground/50' },
    { key: 'overdue', label: 'Overdue', count: props.taskStats.overdue, class: 'bg-destructive' },
    { key: 'cancelled', label: 'Cancelled', count: props.taskStats.cancelled, class: 'bg-muted-foreground/30' },
]);

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

        <PageContainer>
            <!-- Greeting hero -->
            <div class="mb-7 flex flex-col gap-4 border-b border-border/70 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm text-muted-foreground">{{ today }}</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-foreground">
                        {{ greeting }}, {{ firstName }}
                    </h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        <template v-if="taskStats.overdue > 0">
                            You have <span class="font-medium text-destructive">{{ taskStats.overdue }} overdue</span>
                            and {{ taskStats.in_progress }} task{{ taskStats.in_progress !== 1 ? 's' : '' }} in progress.
                        </template>
                        <template v-else-if="taskStats.in_progress > 0">
                            {{ taskStats.in_progress }} task{{ taskStats.in_progress !== 1 ? 's' : '' }} in progress — keep the momentum going.
                        </template>
                        <template v-else>
                            You're all caught up. Nice and clear.
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="createNote">
                        <Plus class="mr-1.5 h-4 w-4" /> New note
                    </Button>
                    <Button size="sm" asChild>
                        <Link :href="route('calendar.index')">
                            <CalendarDays class="mr-1.5 h-4 w-4" /> New event
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard :icon="FolderOpen" label="Projects" :value="projectStats.total" :hint="`${projectStats.active} active`" accent />
                <StatCard :icon="CheckCircle2" label="Tasks" :value="taskStats.total" :hint="`${taskStats.in_progress} in progress`" />
                <StatCard :icon="FileText" label="Notes" :value="noteStats.total" :hint="`${noteStats.pinned} pinned`" />
                <StatCard :icon="CalendarDays" label="Events" :value="calendarStats.total" :hint="`${calendarStats.today} today`" />
            </div>

            <!-- Insights: completion rings + task breakdown -->
            <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="text-base">Completion</CardTitle>
                        <CardDescription>Overall progress</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-around gap-4 pt-2">
                        <RingStat :value="completionRates.tasks" label="Tasks" :sublabel="`${taskStats.completed}/${taskStats.total}`" />
                        <RingStat :value="completionRates.projects" label="Projects" :sublabel="`${projectStats.completed}/${projectStats.total}`" />
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base">Task breakdown</CardTitle>
                        <CardDescription>Status across all tasks</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5 pt-2">
                        <!-- Stacked bar -->
                        <div class="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                v-for="seg in taskSegments"
                                :key="seg.key"
                                v-show="seg.count > 0"
                                class="h-full transition-all"
                                :class="seg.class"
                                :style="{ width: (seg.count / Math.max(taskStats.total, 1)) * 100 + '%' }"
                                :title="`${seg.label}: ${seg.count}`"
                            ></div>
                        </div>
                        <!-- Legend -->
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                            <div v-for="seg in taskSegments" :key="seg.key" class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :class="seg.class"></span>
                                <span class="text-sm text-muted-foreground">{{ seg.label }}</span>
                                <span class="ml-auto text-sm font-semibold text-foreground sm:ml-1">{{ seg.count }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Attention: overdue / due soon (only when present) -->
            <Card v-if="overdueTasks.length > 0 || tasksDueSoon.length > 0" class="mb-6 border-destructive/30">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <AlertTriangle class="h-4 w-4 text-destructive" />
                        Needs attention
                    </CardTitle>
                    <CardDescription>Overdue tasks and deadlines this week</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <Link
                            v-for="task in [...overdueTasks, ...tasksDueSoon].slice(0, 6)"
                            :key="task.id"
                            :href="route('projects.show', task.project?.id)"
                            class="group flex items-center gap-3 rounded-lg border border-border bg-muted/30 px-3 py-2.5 transition-colors hover:border-primary/40 hover:bg-muted/60"
                        >
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="task.days_overdue ? 'bg-destructive' : 'bg-primary'"
                                :style="task.project ? `background-color: ${task.project.color}` : ''"
                            ></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ task.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="task.project">{{ task.project.name }} · </span>
                                    <span :class="task.days_overdue ? 'text-destructive' : 'text-primary'">
                                        {{ task.days_overdue ? `${Math.abs(task.days_overdue)}d overdue` : `due in ${task.days_until_due}d` }}
                                    </span>
                                </p>
                            </div>
                            <ArrowRight class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Main: Recent Tasks (primary) + side rail -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Recent Tasks -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base">Recent tasks</CardTitle>
                                <CardDescription>Latest activity across projects</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('projects.index')" class="text-sm text-primary hover:underline">
                                    All projects <ArrowRight class="ml-1 h-3 w-3" />
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentTasks.length > 0" class="divide-y divide-border">
                            <div v-for="task in recentTasks.slice(0, 6)" :key="task.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                <span v-if="task.project" class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :style="`background-color: ${task.project.color}`"></span>
                                <span v-else class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-muted-foreground/40"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-sm font-medium text-foreground">{{ task.title }}</span>
                                        <Badge :class="getStatusClass(task.status, 'task')" class="shrink-0 px-1.5 py-0.5 text-xs">
                                            {{ task.status.replace('_', ' ') }}
                                        </Badge>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span v-if="task.project" class="text-xs text-muted-foreground">{{ task.project.name }}</span>
                                        <Badge v-if="task.priority" :class="getPriorityClass(task.priority)" variant="outline" class="px-1 py-0 text-xs">
                                            {{ task.priority }}
                                        </Badge>
                                        <Badge v-for="tag in (task.tags || []).slice(0, 2)" :key="tag.id" variant="outline" class="px-1 py-0 text-xs" :style="`border-color: ${tag.color}; color: ${tag.color}`">
                                            {{ tag.name }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground">
                            <Activity class="mb-2 h-8 w-8 opacity-40" />
                            <p class="text-sm">No recent tasks</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Side rail -->
                <div class="space-y-6">
                    <!-- Upcoming events -->
                    <Card>
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-base">Upcoming</CardTitle>
                                <Link :href="route('calendar.index')" class="text-xs text-primary hover:underline">View all</Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="upcomingEvents.length > 0" class="space-y-3">
                                <Link
                                    v-for="event in upcomingEvents.slice(0, 4)"
                                    :key="event.id"
                                    :href="route('calendar.index')"
                                    class="flex items-start gap-2.5"
                                >
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :style="`background-color: ${event.color}`"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-foreground">{{ event.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatEventDate(event.start_date, event.all_day) }}
                                            <span v-if="event.days_until_event >= 0" class="text-primary">
                                                · {{ event.days_until_event === 0 ? 'Today' : `${event.days_until_event}d` }}
                                            </span>
                                        </p>
                                    </div>
                                </Link>
                            </div>
                            <p v-else class="py-4 text-center text-sm text-muted-foreground">No upcoming events</p>
                        </CardContent>
                    </Card>

                    <!-- Active projects -->
                    <Card>
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-base">Projects</CardTitle>
                                <Link :href="route('projects.index')" class="text-xs text-primary hover:underline">View all</Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="recentProjects.length > 0" class="space-y-3">
                                <Link
                                    v-for="project in recentProjects.slice(0, 4)"
                                    :key="project.id"
                                    :href="route('projects.show', project.id)"
                                    class="flex items-center gap-2.5"
                                >
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background-color: ${project.color}`"></span>
                                    <span class="truncate text-sm font-medium text-foreground">{{ project.name }}</span>
                                    <span v-if="typeof project.completion_percentage === 'number'" class="ml-auto text-xs text-muted-foreground">
                                        {{ project.completion_percentage }}%
                                    </span>
                                </Link>
                            </div>
                            <p v-else class="py-4 text-center text-sm text-muted-foreground">No projects yet</p>
                        </CardContent>
                    </Card>

                    <!-- Latest notes -->
                    <Card>
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-base">Notes</CardTitle>
                                <Link :href="route('notes.index')" class="text-xs text-primary hover:underline">View all</Link>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="latestNotes.length > 0" class="space-y-3">
                                <Link
                                    v-for="note in latestNotes.slice(0, 4)"
                                    :key="note.id"
                                    :href="route('notes.show', note.id)"
                                    class="flex items-start gap-2"
                                >
                                    <Pin v-if="note.is_pinned" class="mt-0.5 h-3 w-3 shrink-0 text-primary" />
                                    <FileText v-else class="mt-0.5 h-3 w-3 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-foreground">{{ note.title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ getRelativeTime(note.updated_at) }}</p>
                                    </div>
                                </Link>
                            </div>
                            <p v-else class="py-4 text-center text-sm text-muted-foreground">No notes yet</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageContainer>
    </AppLayout>
</template>
