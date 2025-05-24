<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, AlertCircle, CheckCircle2 } from 'lucide-vue-next';

defineProps({
    project: {
        type: Object,
        required: true
    },
    completionPercentage: {
        type: Number,
        default: 0
    }
});

const formatDate = (date: string | null): string => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString();
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-xs font-medium text-gray-600">Progress</CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xl font-bold">{{ Math.round(completionPercentage || 0) }}%</span>
                        <CheckCircle2 class="h-4 w-4 text-green-600" />
                    </div>
                    <div class="h-2 w-full rounded-full bg-gray-200">
                        <div
                            class="h-2 rounded-full transition-all duration-300"
                            :class="{
                'bg-red-500': (completionPercentage || 0) < 25,
                'bg-orange-500': (completionPercentage || 0) >= 25 && (completionPercentage || 0) < 50,
                'bg-yellow-500': (completionPercentage || 0) >= 50 && (completionPercentage || 0) < 75,
                'bg-green-500': (completionPercentage || 0) >= 75,
              }"
                            :style="`width: ${completionPercentage || 0}%`"
                        ></div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-xs font-medium text-gray-600">Timeline</CardTitle>
            </CardHeader>
            <CardContent class="space-y-1 pt-0">
                <div class="flex items-center text-xs">
                    <Calendar class="mr-2 h-3 w-3 text-gray-400" />
                    <span class="text-gray-600">Start:</span>
                    <span class="ml-1 font-medium">{{ formatDate(project.start_date) || 'Not set' }}</span>
                </div>
                <div class="flex items-center text-xs">
                    <AlertCircle class="mr-2 h-3 w-3 text-gray-400" />
                    <span class="text-gray-600">Due:</span>
                    <span class="ml-1 font-medium">{{ formatDate(project.due_date) || 'Not set' }}</span>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-xs font-medium text-gray-600">Tasks</CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="text-xl font-bold">{{ project.tasks?.length || 0 }}</div>
                <div class="text-xs text-gray-600">
                    {{ project.tasks?.filter((t) => t.status === 'completed').length || 0 }} completed
                </div>
            </CardContent>
        </Card>
    </div>
</template>
