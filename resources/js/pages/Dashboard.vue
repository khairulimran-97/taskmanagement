<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import RingStat from '@/components/RingStat.vue';
import StatCard from '@/components/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, CalendarDays, CheckCircle2, FileText, FolderOpen, Loader2, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

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

const today = computed(() => new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }));

// Guards double-clicks: the endpoint redirects on success, so only errors need surfacing here
const creatingNote = ref(false);
const createNote = () => {
    if (creatingNote.value) return;
    router.post(
        route('notes.create-empty'),
        {},
        {
            onStart: () => (creatingNote.value = true),
            onFinish: () => (creatingNote.value = false),
            onError: () => toast.error('Could not create note', { description: 'Please try again.' }),
        },
    );
};

// Task status breakdown for the stacked bar + legend (status language: completed=success, in progress=primary)
const taskSegments = computed(() => [
    { key: 'completed', label: 'Completed', count: props.taskStats.completed, class: 'bg-success' },
    { key: 'in_progress', label: 'In progress', count: props.taskStats.in_progress, class: 'bg-primary' },
    { key: 'todo', label: 'To do', count: props.taskStats.todo, class: 'bg-chart-5' },
    { key: 'overdue', label: 'Overdue', count: props.taskStats.overdue, class: 'bg-destructive' },
    { key: 'cancelled', label: 'Cancelled', count: props.taskStats.cancelled, class: 'bg-muted-foreground/30' },
]);

// Screen-reader summary for the stacked bar (segments themselves are decorative divs)
const taskBarLabel = computed(
    () =>
        taskSegments.value
            .filter((s) => s.count > 0)
            .map((s) => `${s.label}: ${s.count}`)
            .join(', ') || 'No tasks',
);

// Needs-attention list: overdue first, capped at 6 with a count of what's hidden
const attentionTasks = computed(() => [...props.overdueTasks, ...props.tasksDueSoon]);
const visibleAttentionTasks = computed(() => attentionTasks.value.slice(0, 6));
const hiddenAttentionCount = computed(() => Math.max(0, attentionTasks.value.length - 6));

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

// Status language (style guide): success=done/active, primary=in progress, muted=neutral/paused
const statusClasses: Record<string, string> = {
    active: 'border-transparent bg-success/10 text-success',
    completed: 'border-transparent bg-success/10 text-success',
    in_progress: 'border-transparent bg-primary/10 text-primary',
    paused: 'border-transparent bg-muted text-muted-foreground',
    archived: 'border-transparent bg-muted text-muted-foreground',
    todo: 'border-transparent bg-muted text-muted-foreground',
    cancelled: 'border-transparent bg-muted text-muted-foreground',
};

const getStatusClass = (status: string): string => statusClasses[status] ?? 'border-transparent bg-muted text-muted-foreground';

// Urgent (filled) vs high (outlined) keeps the two red levels distinguishable
const priorityClasses: Record<string, string> = {
    urgent: 'border-transparent bg-destructive/10 text-destructive',
    high: 'border-destructive/30 bg-transparent text-destructive',
    medium: 'border-transparent bg-warning/10 text-warning',
    low: 'border-transparent bg-muted text-muted-foreground',
};

const getPriorityClass = (priority: string): string => priorityClasses[priority] ?? 'border-transparent bg-muted text-muted-foreground';

// Raw enum ("in_progress") → sentence-case label ("In progress")
const sentenceCase = (value: string): string => {
    const text = value.replace(/_/g, ' ');
    return text.charAt(0).toUpperCase() + text.slice(1);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :notifications="notifications">
        <Head title="Dashboard" />

        <PageContainer>
            <!-- Greeting header -->
            <div class="border-border mb-6 flex flex-col gap-4 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-muted-foreground text-sm">{{ today }}</p>
                    <h1 class="text-foreground mt-1 text-xl font-semibold tracking-tight">{{ greeting }}, {{ firstName }}</h1>
                    <p class="text-muted-foreground mt-1.5 text-sm">
                        <template v-if="taskStats.overdue > 0">
                            You have <span class="text-destructive font-medium">{{ taskStats.overdue }} overdue</span> and
                            {{ taskStats.in_progress }} task{{ taskStats.in_progress !== 1 ? 's' : '' }} in progress.
                        </template>
                        <template v-else-if="taskStats.in_progress > 0">
                            {{ taskStats.in_progress }} task{{ taskStats.in_progress !== 1 ? 's' : '' }} in progress — keep the momentum going.
                        </template>
                        <template v-else> You're all caught up. Nice and clear. </template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" :disabled="creatingNote" @click="createNote">
                        <Loader2 v-if="creatingNote" class="animate-spin" />
                        <Plus v-else />
                        {{ creatingNote ? 'Creating…' : 'New note' }}
                    </Button>
                    <Button size="sm" asChild>
                        <Link :href="route('calendar.index')"> <CalendarDays /> Open calendar </Link>
                    </Button>
                </div>
            </div>

            <!-- Stats overview -->
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
                        <CardTitle>Completion</CardTitle>
                        <CardDescription>Overall progress</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-around gap-4">
                        <RingStat :value="completionRates.tasks" label="Tasks" :sublabel="`${taskStats.completed}/${taskStats.total}`" />
                        <RingStat :value="completionRates.projects" label="Projects" :sublabel="`${projectStats.completed}/${projectStats.total}`" />
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Task breakdown</CardTitle>
                        <CardDescription>Status across all tasks</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="taskStats.total > 0" class="space-y-5">
                            <!-- Stacked bar -->
                            <div
                                class="bg-muted flex h-3 w-full overflow-hidden rounded-full"
                                role="img"
                                :aria-label="`Task status — ${taskBarLabel}`"
                            >
                                <template v-for="seg in taskSegments" :key="seg.key">
                                    <div
                                        v-if="seg.count > 0"
                                        class="h-full"
                                        :class="seg.class"
                                        :style="{ width: (seg.count / Math.max(taskStats.total, 1)) * 100 + '%' }"
                                        :title="`${seg.label}: ${seg.count}`"
                                    ></div>
                                </template>
                            </div>
                            <!-- Legend -->
                            <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                                <div v-for="seg in taskSegments" :key="seg.key" class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full" :class="seg.class" aria-hidden="true"></span>
                                    <span class="text-muted-foreground text-sm">{{ seg.label }}</span>
                                    <span class="text-foreground ml-auto text-sm font-semibold tabular-nums sm:ml-1">{{ seg.count }}</span>
                                </div>
                            </div>
                        </div>
                        <EmptyState
                            v-else
                            :icon="CheckCircle2"
                            title="No tasks yet"
                            description="Add tasks to a project to see how work breaks down by status."
                        />
                    </CardContent>
                </Card>
            </div>

            <!-- Attention: overdue / due soon (only when present) -->
            <Card v-if="attentionTasks.length > 0" class="border-destructive/30 mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertTriangle class="text-destructive size-4" />
                        Needs attention
                    </CardTitle>
                    <CardDescription>Overdue tasks and deadlines this week</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <Link
                            v-for="task in visibleAttentionTasks"
                            :key="task.id"
                            :href="task.project ? route('projects.show', task.project.id) : route('projects.index')"
                            class="group border-border bg-muted/30 hover:border-muted-foreground/30 hover:bg-muted/60 focus-visible:ring-ring/50 flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <!-- Dot carries the status signal only: overdue=destructive, due soon=warning -->
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :class="task.days_overdue ? 'bg-destructive' : 'bg-warning'"
                                aria-hidden="true"
                            ></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-foreground truncate text-sm font-medium">{{ task.title }}</p>
                                <p class="text-muted-foreground text-xs">
                                    <span v-if="task.project">{{ task.project.name }} · </span>
                                    <span class="tabular-nums" :class="task.days_overdue ? 'text-destructive' : 'text-warning'">
                                        {{ task.days_overdue ? `${Math.abs(task.days_overdue)}d overdue` : `due in ${task.days_until_due}d` }}
                                    </span>
                                </p>
                            </div>
                            <ArrowRight
                                class="text-muted-foreground/50 group-hover:text-primary size-3.5 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5"
                            />
                        </Link>
                    </div>
                    <p v-if="hiddenAttentionCount > 0" class="text-muted-foreground mt-3 text-xs">
                        +{{ hiddenAttentionCount }} more overdue or due soon
                    </p>
                </CardContent>
            </Card>

            <!-- Main: recent tasks + projects (balanced) -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Recent tasks -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <CardTitle>Recent tasks</CardTitle>
                                <CardDescription>Latest activity across projects</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('projects.index')"> View projects <ArrowRight class="size-3.5" /> </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentTasks.length > 0" class="divide-border -mx-5 flex flex-col divide-y">
                            <Link
                                v-for="task in recentTasks.slice(0, 6)"
                                :key="task.id"
                                :href="task.project ? route('projects.show', task.project.id) : route('projects.index')"
                                class="group hover:bg-muted/60 focus-visible:ring-ring/50 flex min-h-10 items-start gap-3 px-5 py-2.5 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                            >
                                <span
                                    v-if="task.project"
                                    class="mt-1.5 size-2 shrink-0 rounded-full"
                                    :style="`background-color: ${task.project.color}`"
                                    aria-hidden="true"
                                ></span>
                                <span v-else class="bg-muted-foreground/40 mt-1.5 size-2 shrink-0 rounded-full" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span
                                            class="text-foreground group-hover:text-primary truncate text-sm font-medium transition-colors duration-150"
                                            >{{ task.title }}</span
                                        >
                                        <Badge :class="getStatusClass(task.status)" class="shrink-0 px-1.5 py-0.5 text-xs">
                                            {{ sentenceCase(task.status) }}
                                        </Badge>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <span v-if="task.project" class="text-muted-foreground text-xs">{{ task.project.name }}</span>
                                        <Badge v-if="task.priority" :class="getPriorityClass(task.priority)" class="px-1 py-0 text-xs">
                                            {{ sentenceCase(task.priority) }}
                                        </Badge>
                                        <Badge
                                            v-for="tag in (task.tags || []).slice(0, 2)"
                                            :key="tag.id"
                                            variant="outline"
                                            class="px-1 py-0 text-xs"
                                            :style="`border-color: ${tag.color}; color: ${tag.color}`"
                                        >
                                            {{ tag.name }}
                                        </Badge>
                                    </div>
                                </div>
                                <ArrowRight
                                    class="text-muted-foreground/50 group-hover:text-primary mt-0.5 size-3.5 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5"
                                />
                            </Link>
                        </div>
                        <EmptyState
                            v-else
                            :icon="CheckCircle2"
                            title="No recent tasks"
                            description="Tasks you create inside projects will show up here."
                        >
                            <template #action>
                                <Button variant="outline" size="sm" asChild>
                                    <Link :href="route('projects.index')"> <ArrowRight class="size-3.5" /> Go to projects </Link>
                                </Button>
                            </template>
                        </EmptyState>
                    </CardContent>
                </Card>

                <!-- Projects -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <CardTitle>Projects</CardTitle>
                                <CardDescription>Your most recent projects</CardDescription>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link :href="route('projects.index')"> View all <ArrowRight class="size-3.5" /> </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentProjects.length > 0" class="divide-border -mx-5 flex flex-col divide-y">
                            <Link
                                v-for="project in recentProjects.slice(0, 6)"
                                :key="project.id"
                                :href="route('projects.show', project.id)"
                                class="group hover:bg-muted/60 focus-visible:ring-ring/50 flex min-h-10 items-center gap-3 px-5 py-2.5 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                            >
                                <span class="size-2.5 shrink-0 rounded-full" :style="`background-color: ${project.color}`" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <span
                                        class="text-foreground group-hover:text-primary truncate text-sm font-medium transition-colors duration-150"
                                        >{{ project.name }}</span
                                    >
                                    <div class="mt-1 flex items-center gap-2">
                                        <Badge :class="getStatusClass(project.status)" class="px-1.5 py-0.5 text-xs">
                                            {{ sentenceCase(project.status) }}
                                        </Badge>
                                        <span class="text-muted-foreground text-xs">{{ getRelativeTime(project.created_at) }}</span>
                                    </div>
                                </div>
                                <span
                                    v-if="typeof project.completion_percentage === 'number'"
                                    class="text-muted-foreground shrink-0 text-xs font-semibold tabular-nums"
                                >
                                    {{ project.completion_percentage }}%
                                </span>
                                <ArrowRight
                                    class="text-muted-foreground/50 group-hover:text-primary size-3.5 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5"
                                />
                            </Link>
                        </div>
                        <EmptyState v-else :icon="FolderOpen" title="No projects yet" description="Create a project to start organizing tasks.">
                            <template #action>
                                <Button variant="outline" size="sm" asChild>
                                    <Link :href="route('projects.index')"> <Plus class="size-3.5" /> Create project </Link>
                                </Button>
                            </template>
                        </EmptyState>
                    </CardContent>
                </Card>
            </div>
        </PageContainer>
    </AppLayout>
</template>
