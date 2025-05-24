<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps({
    project: {
        type: Object,
        required: true
    }
});

const getProjectStatusClass = (status: string): string => {
    switch (status) {
        case 'active':
            return 'bg-blue-100 text-blue-800';
        case 'paused':
            return 'bg-orange-100 text-orange-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'archived':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getProjectPriorityClass = (priority: string): string => {
    switch (priority) {
        case 'high':
            return 'bg-red-100 text-red-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'low':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <div class="mb-3 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="h-3 w-3 rounded-full border border-white shadow-sm" :style="`background-color: ${project.color}`"></div>
            <h1 class="text-2xl font-bold text-gray-900">{{ project.name }}</h1>
        </div>

        <div class="flex items-center space-x-2">
            <Badge :class="getProjectStatusClass(project.status)" class="px-2 py-1 text-xs">
                {{ project.status.charAt(0).toUpperCase() + project.status.slice(1) }}
            </Badge>
            <Badge :class="getProjectPriorityClass(project.priority)" class="px-2 py-1 text-xs">
                {{ project.priority.charAt(0).toUpperCase() + project.priority.slice(1) }}
            </Badge>
        </div>
    </div>

    <div v-if="project.description" class="mt-3">
        <p class="text-sm text-gray-600">{{ project.description }}</p>
    </div>
</template>
