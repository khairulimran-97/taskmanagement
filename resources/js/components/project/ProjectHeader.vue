<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Flag, Pencil, Plus } from 'lucide-vue-next';
import { projectStatusDot, projectStatusBadge, priorityText, labelize } from '@/lib/projectMeta';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits<{
    (e: 'edit'): void;
    (e: 'add-task'): void;
}>();

const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <div class="space-y-3">
        <!-- Back link -->
        <Link
            :href="route('projects.index')"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-primary"
        >
            <ArrowLeft class="h-3.5 w-3.5" />
            All projects
        </Link>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3">
                    <span
                        class="h-5 w-5 flex-shrink-0 rounded-md ring-1 ring-border"
                        :style="`background-color: ${project.color}`"
                    ></span>
                    <h1 class="truncate font-display text-2xl font-semibold tracking-tight text-foreground">
                        {{ project.name }}
                    </h1>
                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
                        :class="projectStatusBadge(project.status)"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="projectStatusDot(project.status)"></span>
                        {{ labelize(project.status) }}
                    </span>
                </div>

                <p v-if="project.description" class="mt-1.5 pl-8 text-sm text-muted-foreground">
                    {{ project.description }}
                </p>

                <div class="mt-2 flex items-center gap-4 pl-8 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1" :class="priorityText(project.priority)">
                        <Flag class="h-3 w-3" />
                        {{ labelize(project.priority) }} priority
                    </span>
                    <span v-if="project.due_date" class="inline-flex items-center gap-1">
                        <CalendarDays class="h-3 w-3" />
                        Due {{ formatDate(project.due_date) }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-shrink-0 items-center gap-2">
                <Button variant="outline" size="sm" @click="emit('edit')">
                    <Pencil class="mr-1.5 h-4 w-4" />
                    Edit
                </Button>
                <Button size="sm" @click="emit('add-task')">
                    <Plus class="mr-1.5 h-4 w-4" />
                    New issue
                </Button>
            </div>
        </div>
    </div>
</template>
