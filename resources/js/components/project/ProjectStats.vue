<script setup lang="ts">
import { CalendarDays, CheckCircle2, Circle, Clock, ListTodo } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    completionPercentage: {
        type: Number,
        default: 0,
    },
});

const rootTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id) || []);
const totalTasks = computed(() => rootTasks.value.length);
const completedTasks = computed(() => rootTasks.value.filter((t: any) => t.status === 'completed').length);
const inProgressTasks = computed(() => rootTasks.value.filter((t: any) => t.status === 'in_progress').length);
const todoTasks = computed(() => rootTasks.value.filter((t: any) => t.status === 'todo' || !t.status).length);
const pct = computed(() => Math.round(props.completionPercentage || 0));

const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
};

const dateRange = computed(() => {
    const s = formatDate(props.project.start_date);
    const e = formatDate(props.project.due_date);
    if (s && e) return `${s} → ${e}`;
    if (e) return `Due ${e}`;
    if (s) return `From ${s}`;
    return null;
});

const stats = computed(() => [
    { key: 'total', label: 'Tasks', value: totalTasks.value, icon: ListTodo, tint: 'text-muted-foreground' },
    { key: 'todo', label: 'To do', value: todoTasks.value, icon: Circle, tint: 'text-muted-foreground' },
    { key: 'active', label: 'Active', value: inProgressTasks.value, icon: Clock, tint: 'text-primary' },
    { key: 'done', label: 'Done', value: completedTasks.value, icon: CheckCircle2, tint: 'text-success' },
]);
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- Progress (spans 2) -->
        <div class="border-border bg-card col-span-2 flex flex-col justify-center rounded-lg border px-4 py-3 shadow-xs">
            <div class="mb-1.5 flex items-baseline justify-between">
                <span class="text-muted-foreground text-xs font-medium">Progress</span>
                <span class="text-foreground text-lg leading-none font-semibold tabular-nums">{{ pct }}%</span>
            </div>
            <div class="bg-muted h-2 w-full overflow-hidden rounded-full">
                <div class="bg-primary h-full rounded-full transition-all duration-300" :style="`width: ${pct}%`"></div>
            </div>
        </div>

        <!-- Stat cells -->
        <div v-for="s in stats" :key="s.key" class="border-border bg-card flex items-center gap-2.5 rounded-lg border px-3 py-3 shadow-xs">
            <div class="bg-muted flex size-8 shrink-0 items-center justify-center rounded-md">
                <component :is="s.icon" class="size-4" :class="s.tint" />
            </div>
            <div class="min-w-0">
                <p class="text-foreground text-lg leading-none font-semibold tabular-nums">{{ s.value }}</p>
                <p class="text-muted-foreground mt-0.5 truncate text-xs">{{ s.label }}</p>
            </div>
        </div>
    </div>

    <!-- Timeline row -->
    <div v-if="dateRange" class="text-muted-foreground mt-3 flex items-center gap-1.5 px-1 text-xs tabular-nums">
        <CalendarDays class="size-3.5" />
        <span>{{ dateRange }}</span>
    </div>
</template>
