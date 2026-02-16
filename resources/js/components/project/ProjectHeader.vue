<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { CalendarDays, Flag } from 'lucide-vue-next';

defineProps({
    project: {
        type: Object,
        required: true
    }
});

const statusLabel = (status: string) => {
    const labels: Record<string, string> = { active: 'Active', paused: 'Paused', completed: 'Completed', archived: 'Archived' };
    return labels[status] || status;
};

const statusDot = (status: string) => {
    const colors: Record<string, string> = { active: 'bg-green-500', paused: 'bg-yellow-500', completed: 'bg-blue-500', archived: 'bg-gray-400' };
    return colors[status] || 'bg-gray-400';
};

const priorityLabel = (priority: string) => {
    const labels: Record<string, string> = { high: 'High', medium: 'Medium', low: 'Low' };
    return labels[priority] || priority;
};

const priorityColor = (priority: string) => {
    const colors: Record<string, string> = { high: 'text-orange-600 dark:text-orange-400', medium: 'text-yellow-600 dark:text-yellow-400', low: 'text-green-600 dark:text-green-400' };
    return colors[priority] || 'text-gray-500';
};

const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-3">
                <div class="h-4 w-4 flex-shrink-0 rounded" :style="`background-color: ${project.color}`"></div>
                <h1 class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ project.name }}</h1>
                <div class="flex items-center gap-1.5 rounded-full border border-gray-200 px-2 py-0.5 dark:border-gray-700">
                    <span class="h-1.5 w-1.5 rounded-full" :class="statusDot(project.status)"></span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ statusLabel(project.status) }}</span>
                </div>
            </div>
            <p v-if="project.description" class="mt-1 pl-7 text-sm text-gray-500 dark:text-gray-400">{{ project.description }}</p>
        </div>

        <div class="flex flex-shrink-0 items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
            <div class="flex items-center gap-1" :class="priorityColor(project.priority)">
                <Flag class="h-3 w-3" />
                <span>{{ priorityLabel(project.priority) }}</span>
            </div>
            <div v-if="project.due_date" class="flex items-center gap-1">
                <CalendarDays class="h-3 w-3" />
                <span>{{ formatDate(project.due_date) }}</span>
            </div>
        </div>
    </div>
</template>
