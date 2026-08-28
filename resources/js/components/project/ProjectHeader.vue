<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { labelize } from '@/lib/projectMeta';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Flag, Pencil, Plus } from 'lucide-vue-next';

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

// Status language — identical to the task views (tokens only)
const statusMeta = (status: string) => {
    const map: Record<string, { badge: string; dot: string }> = {
        active: { badge: 'bg-primary/10 text-primary', dot: 'bg-primary' },
        completed: { badge: 'bg-success/10 text-success', dot: 'bg-success' },
        paused: { badge: 'bg-muted text-muted-foreground', dot: 'bg-muted-foreground/50' },
        archived: { badge: 'bg-muted text-muted-foreground', dot: 'bg-muted-foreground/50' },
    };
    return map[status] || { badge: 'bg-muted text-muted-foreground', dot: 'bg-muted-foreground/50' };
};

const priorityTextClass = (priority: string) => {
    const map: Record<string, string> = {
        urgent: 'text-destructive',
        high: 'text-warning',
        medium: 'text-warning',
        low: 'text-muted-foreground',
    };
    return map[priority] || 'text-muted-foreground';
};
</script>

<template>
    <div class="space-y-3">
        <!-- Back link -->
        <Link
            :href="route('projects.index')"
            class="text-muted-foreground hover:text-primary focus-visible:ring-ring/50 inline-flex items-center gap-1.5 rounded-sm text-sm transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
        >
            <ArrowLeft class="size-3.5" />
            All projects
        </Link>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3">
                    <span class="ring-border size-4 flex-shrink-0 rounded-full ring-1" :style="`background-color: ${project.color}`"></span>
                    <h1 class="text-foreground truncate text-xl font-semibold tracking-tight">
                        {{ project.name }}
                    </h1>
                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="statusMeta(project.status).badge"
                    >
                        <span class="size-1.5 rounded-full" :class="statusMeta(project.status).dot"></span>
                        {{ labelize(project.status) }}
                    </span>
                </div>

                <p v-if="project.description" class="text-muted-foreground mt-1.5 pl-7 text-sm">
                    {{ project.description }}
                </p>

                <div class="text-muted-foreground mt-2 flex items-center gap-4 pl-7 text-xs">
                    <span class="inline-flex items-center gap-1" :class="priorityTextClass(project.priority)">
                        <Flag class="size-3" />
                        {{ labelize(project.priority) }} priority
                    </span>
                    <span v-if="project.due_date" class="inline-flex items-center gap-1">
                        <CalendarDays class="size-3" />
                        Due {{ formatDate(project.due_date) }}
                    </span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-shrink-0 items-center gap-2">
                <Button variant="outline" size="sm" @click="emit('edit')">
                    <Pencil class="size-4" />
                    Edit
                </Button>
                <Button size="sm" @click="emit('add-task')">
                    <Plus class="size-4" />
                    New task
                </Button>
            </div>
        </div>
    </div>
</template>
