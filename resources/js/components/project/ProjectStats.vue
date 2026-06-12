<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, ListTodo, Clock, Circle, CalendarDays } from 'lucide-vue-next';
import { progressColor as progressRamp } from '@/lib/projectMeta';

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

const barColor = computed(() => progressRamp(pct.value));

const stats = computed(() => [
    { key: 'total', label: 'Tasks', value: totalTasks.value, icon: ListTodo, tint: 'text-muted-foreground' },
    { key: 'todo', label: 'To do', value: todoTasks.value, icon: Circle, tint: 'text-muted-foreground' },
    { key: 'active', label: 'Active', value: inProgressTasks.value, icon: Clock, tint: 'text-primary' },
    { key: 'done', label: 'Done', value: completedTasks.value, icon: CheckCircle2, tint: 'text-[hsl(150_24%_45%)]' },
]);
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- Progress (spans 2) -->
        <div class="col-span-2 flex flex-col justify-center rounded-xl border border-border bg-card px-4 py-3">
            <div class="mb-1.5 flex items-baseline justify-between">
                <span class="text-xs font-medium text-muted-foreground">Progress</span>
                <span class="font-display text-lg font-semibold leading-none text-foreground">{{ pct }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full transition-all duration-700" :class="barColor" :style="`width: ${pct}%`"></div>
            </div>
        </div>

        <!-- Stat cells -->
        <div
            v-for="s in stats"
            :key="s.key"
            class="flex items-center gap-2.5 rounded-xl border border-border bg-card px-3 py-3"
        >
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                <component :is="s.icon" class="h-4 w-4" :class="s.tint" />
            </div>
            <div class="min-w-0">
                <p class="font-display text-lg font-semibold leading-none text-foreground">{{ s.value }}</p>
                <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ s.label }}</p>
            </div>
        </div>
    </div>

    <!-- Timeline row -->
    <div v-if="dateRange" class="mt-3 flex items-center gap-1.5 px-1 text-xs text-muted-foreground">
        <CalendarDays class="h-3.5 w-3.5" />
        <span>{{ dateRange }}</span>
    </div>
</template>
