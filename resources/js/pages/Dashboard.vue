<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import RingStat from '@/components/RingStat.vue';
import StatCard from '@/components/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, CalendarDays, CheckCircle2, ChevronRight, Clock, FileText, FolderOpen, Loader2, Plus } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
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
    const h = now.value.getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
});

// Live clock — ticks every second; date/greeting follow it across midnight/hour boundaries
const now = ref(new Date());
let clockTimer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    clockTimer = setInterval(() => (now.value = new Date()), 1000);
});
onUnmounted(() => clearInterval(clockTimer));

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

// Needs-attention tiles (status language: overdue=destructive, due soon=warning, today=primary)
const attentionTiles = computed(() =>
    [
        {
            key: 'overdue',
            count: props.taskStats.overdue,
            label: props.taskStats.overdue === 1 ? 'overdue task' : 'overdue tasks',
            sub: 'Needs attention now',
            href: route('projects.index'),
            icon: AlertTriangle,
            chip: 'bg-destructive/10 text-destructive',
        },
        {
            key: 'due_soon',
            count: props.taskStats.due_soon,
            label: props.taskStats.due_soon === 1 ? 'task due soon' : 'tasks due soon',
            sub: 'Due within 7 days',
            href: route('projects.index'),
            icon: Clock,
            chip: 'bg-warning/10 text-warning',
        },
        {
            key: 'today_events',
            count: props.calendarStats.today,
            label: props.calendarStats.today === 1 ? 'event today' : 'events today',
            sub: 'On your calendar',
            href: route('calendar.index'),
            icon: CalendarDays,
            chip: 'bg-primary/10 text-primary',
        },
    ].filter((t) => t.count > 0),
);

// Task status breakdown for the stacked bar + legend (status language: completed=success, in progress=primary)
const taskSegments = computed(() =>
    [
        { key: 'completed', label: 'Completed', count: props.taskStats.completed, class: 'bg-success' },
        { key: 'in_progress', label: 'In progress', count: props.taskStats.in_progress, class: 'bg-primary' },
        { key: 'todo', label: 'To do', count: props.taskStats.todo, class: 'bg-chart-5' },
        { key: 'overdue', label: 'Overdue', count: props.taskStats.overdue, class: 'bg-destructive' },
        { key: 'cancelled', label: 'Cancelled', count: props.taskStats.cancelled, class: 'bg-muted-foreground/30' },
    ].map((s) => ({
        ...s,
        pct: props.taskStats.total > 0 ? (s.count / props.taskStats.total) * 100 : 0,
    })),
);

// Hover/focus in the bar or legend highlights the same status in both (two-way linking)
const hoveredSegment = ref<string | null>(null);
const segmentDimmed = (key: string): boolean => hoveredSegment.value !== null && hoveredSegment.value !== key;

// Bar fills in on first paint; motion-reduce turns the transition off
const barMounted = ref(false);
onMounted(() => requestAnimationFrame(() => (barMounted.value = true)));

// Distribution rows — priority language: urgent=filled red, high=half red, medium=warning, low=muted
const priorityRows = computed(() => {
    const d = props.taskPriorityDistribution;
    return [
        { key: 'urgent', label: 'Urgent', count: d.urgent, class: 'bg-destructive' },
        { key: 'high', label: 'High', count: d.high, class: 'bg-destructive/50' },
        { key: 'medium', label: 'Medium', count: d.medium, class: 'bg-warning' },
        { key: 'low', label: 'Low', count: d.low, class: 'bg-muted-foreground/40' },
    ];
});
const priorityTotal = computed(() => priorityRows.value.reduce((sum, r) => sum + r.count, 0));

const projectRows = computed(() => {
    const s = props.projectStats;
    return [
        { key: 'active', label: 'Active', count: s.active, class: 'bg-primary' },
        { key: 'completed', label: 'Completed', count: s.completed, class: 'bg-success' },
        { key: 'paused', label: 'Paused', count: s.paused, class: 'bg-warning' },
        { key: 'archived', label: 'Archived', count: s.archived, class: 'bg-muted-foreground/40' },
    ];
});

const barWidth = (count: number, total: number): string => (total > 0 ? `${Math.max((count / total) * 100, count > 0 ? 3 : 0)}%` : '0%');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :notifications="notifications">
        <Head title="Dashboard" />

        <PageContainer>
            <!-- Greeting header -->
            <div class="border-border mb-6 flex flex-col gap-4 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-foreground text-xl font-semibold tracking-tight">{{ greeting }}, {{ firstName }}</h1>
                    <p v-if="taskStats.overdue > 0 || taskStats.in_progress > 0" class="text-muted-foreground mt-1.5 text-sm">
                        <template v-if="taskStats.overdue > 0">
                            You have <span class="text-destructive font-medium">{{ taskStats.overdue }} overdue</span> and
                            {{ taskStats.in_progress }} task{{ taskStats.in_progress !== 1 ? 's' : '' }} in progress.
                        </template>
                        <template v-else>
                            {{ taskStats.in_progress }} task{{ taskStats.in_progress !== 1 ? 's' : '' }} in progress — keep the momentum going.
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" :disabled="creatingNote" @click="createNote">
                        <Loader2 v-if="creatingNote" class="animate-spin" />
                        <Plus v-else />
                        {{ creatingNote ? 'Creating…' : 'New note' }}
                    </Button>
                    <Button asChild>
                        <Link :href="route('calendar.index')"> <CalendarDays /> Open calendar </Link>
                    </Button>
                </div>
            </div>

            <!-- Needs attention -->
            <div v-if="attentionTiles.length > 0" class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Link
                    v-for="tile in attentionTiles"
                    :key="tile.key"
                    :href="tile.href"
                    class="border-border bg-card hover:border-muted-foreground/30 focus-visible:ring-ring/50 group flex items-center gap-3 rounded-lg border p-4 shadow-xs transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <span :class="['flex size-9 shrink-0 items-center justify-center rounded-md', tile.chip]">
                        <component :is="tile.icon" class="size-4.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="text-foreground block text-sm font-semibold tabular-nums"> {{ tile.count }} {{ tile.label }} </span>
                        <span class="text-muted-foreground block text-xs">{{ tile.sub }}</span>
                    </span>
                    <ChevronRight class="text-muted-foreground size-4 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5" />
                </Link>
            </div>

            <!-- Stats overview -->
            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard :icon="FolderOpen" label="Projects" :value="projectStats.total" :hint="`${projectStats.active} active`" accent />
                <StatCard :icon="CheckCircle2" label="Tasks" :value="taskStats.total" :hint="`${taskStats.in_progress} in progress`" />
                <StatCard :icon="FileText" label="Notes" :value="noteStats.total" :hint="`${noteStats.pinned} pinned`" />
                <StatCard :icon="CalendarDays" label="Events" :value="calendarStats.total" :hint="`${calendarStats.today} today`" />
            </div>

            <!-- Task breakdown: slim full-width strip -->
            <Card class="mb-6 gap-3 py-4">
                <CardHeader class="flex-row items-center justify-between">
                    <CardTitle class="text-sm">Task breakdown</CardTitle>
                    <span class="text-muted-foreground text-xs">{{ taskStats.total }} tasks</span>
                </CardHeader>
                <CardContent>
                    <TooltipProvider :delay-duration="150">
                        <div class="bg-muted flex h-3 overflow-hidden rounded-full">
                            <Tooltip v-for="segment in taskSegments.filter((s) => s.count > 0)" :key="segment.key">
                                <TooltipTrigger as-child>
                                    <span
                                        tabindex="0"
                                        class="focus-visible:ring-ring/70 h-full transition-[width,opacity] duration-500 ease-out outline-none focus-visible:ring-2 motion-reduce:transition-none"
                                        :class="[segment.class, segmentDimmed(segment.key) ? 'opacity-30' : 'opacity-100']"
                                        :style="{ width: barMounted ? `${segment.pct}%` : '0%' }"
                                        :aria-label="`${segment.label}: ${segment.count} tasks (${Math.round(segment.pct)}%)`"
                                        @mouseenter="hoveredSegment = segment.key"
                                        @mouseleave="hoveredSegment = null"
                                        @focus="hoveredSegment = segment.key"
                                        @blur="hoveredSegment = null"
                                    ></span>
                                </TooltipTrigger>
                                <TooltipContent> {{ segment.label }} · {{ segment.count }} ({{ Math.round(segment.pct) }}%) </TooltipContent>
                            </Tooltip>
                        </div>
                    </TooltipProvider>
                    <div class="mt-2.5 -mb-1 flex flex-wrap items-center gap-x-1 gap-y-0.5">
                        <button
                            v-for="segment in taskSegments"
                            :key="segment.key"
                            type="button"
                            class="hover:bg-muted/60 focus-visible:ring-ring/50 flex cursor-default items-center gap-1.5 rounded-md px-2 py-1 text-sm transition-[background-color,opacity] duration-150 focus-visible:ring-2 focus-visible:outline-none"
                            :class="segmentDimmed(segment.key) ? 'opacity-40' : ''"
                            @mouseenter="hoveredSegment = segment.key"
                            @mouseleave="hoveredSegment = null"
                            @focus="hoveredSegment = segment.key"
                            @blur="hoveredSegment = null"
                        >
                            <span :class="['size-1.5 shrink-0 rounded-full', segment.class]"></span>
                            <span class="text-muted-foreground">{{ segment.label }}</span>
                            <span class="text-foreground font-medium tabular-nums">{{ segment.count }}</span>
                            <span class="text-muted-foreground text-xs tabular-nums">· {{ Math.round(segment.pct) }}%</span>
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- Completion + distributions in one balanced row -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Completion</CardTitle>
                        <CardDescription>Overall progress</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-around gap-3">
                        <RingStat :value="completionRates.tasks" label="Tasks" :sublabel="`${taskStats.completed}/${taskStats.total}`" :size="96" />
                        <RingStat
                            :value="completionRates.projects"
                            label="Projects"
                            :sublabel="`${projectStats.completed}/${projectStats.total}`"
                            :size="96"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Task priorities</CardTitle>
                        <CardDescription>Where the pressure sits</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3.5">
                        <div v-for="row in priorityRows" :key="row.key">
                            <div class="mb-1.5 flex items-center gap-2 text-sm">
                                <span :class="['size-1.5 shrink-0 rounded-full', row.class]"></span>
                                <span class="text-muted-foreground">{{ row.label }}</span>
                                <span class="text-foreground ml-auto font-medium tabular-nums">{{ row.count }}</span>
                            </div>
                            <div class="bg-muted h-1.5 overflow-hidden rounded-full">
                                <div :class="['h-full rounded-full', row.class]" :style="{ width: barWidth(row.count, priorityTotal) }"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Projects</CardTitle>
                        <CardDescription>By status</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3.5">
                        <div v-for="row in projectRows" :key="row.key">
                            <div class="mb-1.5 flex items-center gap-2 text-sm">
                                <span :class="['size-1.5 shrink-0 rounded-full', row.class]"></span>
                                <span class="text-muted-foreground">{{ row.label }}</span>
                                <span class="text-foreground ml-auto font-medium tabular-nums">{{ row.count }}</span>
                            </div>
                            <div class="bg-muted h-1.5 overflow-hidden rounded-full">
                                <div :class="['h-full rounded-full', row.class]" :style="{ width: barWidth(row.count, projectStats.total) }"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Schedule</CardTitle>
                        <CardDescription>Your calendar at a glance</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="divide-border divide-y">
                            <div class="flex items-center justify-between py-2.5 first:pt-0">
                                <span class="text-muted-foreground text-sm">Today</span>
                                <span class="text-foreground text-lg font-semibold tabular-nums">{{ calendarStats.today }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5">
                                <span class="text-muted-foreground text-sm">This week</span>
                                <span class="text-foreground text-lg font-semibold tabular-nums">{{ calendarStats.this_week }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2.5 last:pb-0">
                                <span class="text-muted-foreground text-sm">All events</span>
                                <span class="text-foreground text-lg font-semibold tabular-nums">{{ calendarStats.total }}</span>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" class="mt-4 w-full" asChild>
                            <Link :href="route('calendar.index')"> <CalendarDays /> Open calendar </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PageContainer>
    </AppLayout>
</template>
