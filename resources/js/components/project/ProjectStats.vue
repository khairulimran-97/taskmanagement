<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, ListTodo, Clock, CalendarDays } from 'lucide-vue-next';
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

const totalTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id).length || 0);
const completedTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id && t.status === 'completed').length || 0);
const inProgressTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id && t.status === 'in_progress').length || 0);
const pct = computed(() => Math.round(props.completionPercentage || 0));

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
};

const barColor = computed(() => progressRamp(pct.value));
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-xl border border-border bg-card px-4 py-3">
        <!-- Progress bar -->
        <div class="flex min-w-[140px] items-center gap-3">
            <div class="h-2 w-24 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full transition-all duration-500" :class="barColor" :style="`width: ${pct}%`"></div>
            </div>
            <span class="font-display text-sm font-semibold text-foreground">{{ pct }}%</span>
        </div>

        <div class="hidden h-5 w-px bg-border sm:block"></div>

        <!-- Stats -->
        <div class="flex items-center gap-5 text-xs text-muted-foreground">
            <div class="flex items-center gap-1.5">
                <ListTodo class="h-3.5 w-3.5" />
                <span><span class="font-semibold text-foreground">{{ totalTasks }}</span> tasks</span>
            </div>
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="h-3.5 w-3.5 text-[hsl(150_24%_45%)]" />
                <span><span class="font-semibold text-foreground">{{ completedTasks }}</span> done</span>
            </div>
            <div class="flex items-center gap-1.5">
                <Clock class="h-3.5 w-3.5 text-primary" />
                <span><span class="font-semibold text-foreground">{{ inProgressTasks }}</span> active</span>
            </div>
        </div>

        <div class="hidden h-5 w-px bg-border sm:block"></div>

        <!-- Timeline -->
        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
            <CalendarDays class="h-3.5 w-3.5" />
            <span>{{ formatDate(project.start_date) }}</span>
            <span v-if="project.start_date && project.due_date">→</span>
            <span v-if="project.due_date">{{ formatDate(project.due_date) }}</span>
        </div>
    </div>
</template>
