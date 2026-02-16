<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircle2, ListTodo, Clock, CalendarDays } from 'lucide-vue-next';

const props = defineProps({
    project: {
        type: Object,
        required: true
    },
    completionPercentage: {
        type: Number,
        default: 0
    }
});

const totalTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id).length || 0);
const completedTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id && t.status === 'completed').length || 0);
const inProgressTasks = computed(() => props.project.tasks?.filter((t: any) => !t.parent_task_id && t.status === 'in_progress').length || 0);
const pct = computed(() => Math.round(props.completionPercentage || 0));

const formatDate = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
};

const progressColor = computed(() => {
    if (pct.value >= 75) return 'bg-green-500';
    if (pct.value >= 50) return 'bg-yellow-500';
    if (pct.value >= 25) return 'bg-orange-500';
    return 'bg-red-500';
});
</script>

<template>
    <div class="flex items-center gap-6 rounded-lg border border-gray-100 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/30">
        <!-- Progress bar -->
        <div class="flex min-w-[140px] items-center gap-3">
            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-full rounded-full transition-all duration-300" :class="progressColor" :style="`width: ${pct}%`"></div>
            </div>
            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ pct }}%</span>
        </div>

        <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>

        <!-- Stats -->
        <div class="flex items-center gap-5 text-xs text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-1.5">
                <ListTodo class="h-3.5 w-3.5" />
                <span><span class="font-medium text-gray-700 dark:text-gray-300">{{ totalTasks }}</span> tasks</span>
            </div>
            <div class="flex items-center gap-1.5">
                <CheckCircle2 class="h-3.5 w-3.5 text-green-500" />
                <span><span class="font-medium text-gray-700 dark:text-gray-300">{{ completedTasks }}</span> done</span>
            </div>
            <div class="flex items-center gap-1.5">
                <Clock class="h-3.5 w-3.5 text-blue-500" />
                <span><span class="font-medium text-gray-700 dark:text-gray-300">{{ inProgressTasks }}</span> active</span>
            </div>
        </div>

        <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>

        <!-- Timeline -->
        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <CalendarDays class="h-3.5 w-3.5" />
            <span>{{ formatDate(project.start_date) }}</span>
            <span v-if="project.start_date && project.due_date">→</span>
            <span v-if="project.due_date">{{ formatDate(project.due_date) }}</span>
        </div>
    </div>
</template>
