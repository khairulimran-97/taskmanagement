<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { CalendarDays, CheckCircle2, ChevronsDown, Circle, Columns3, Edit, Eye, Loader2, MoreVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    updatingTasks: {
        type: Object,
        required: true,
    },
    searchQuery: {
        type: String,
        default: '',
    },
    filterPriority: {
        type: String,
        default: 'all',
    },
});

defineEmits(['add-task', 'view-task', 'toggle-task', 'delete-task', 'view-tags', 'edit-task']);

// Shared status language — dot color per status (tokens only)
const columns = [
    { key: 'todo', label: 'To do', dotClass: 'bg-muted-foreground/50' },
    { key: 'in_progress', label: 'In progress', dotClass: 'bg-primary' },
    { key: 'completed', label: 'Completed', dotClass: 'bg-success' },
    { key: 'cancelled', label: 'Cancelled', dotClass: 'bg-destructive' },
];

const filteredRootTasks = computed(() => {
    let tasks = (props.project.tasks || []).filter((t: any) => !t.parent_task_id);

    if (props.searchQuery) {
        const q = props.searchQuery.toLowerCase();
        tasks = tasks.filter((t: any) => t.title.toLowerCase().includes(q) || (t.description && t.description.toLowerCase().includes(q)));
    }

    if (props.filterPriority && props.filterPriority !== 'all') {
        tasks = tasks.filter((t: any) => t.priority === props.filterPriority);
    }

    return tasks;
});

// True when the project has tasks but the current filters hide them all
const hasRootTasks = computed(() => (props.project.tasks || []).some((t: any) => !t.parent_task_id));

const tasksByStatus = computed(() => {
    const grouped: Record<string, any[]> = { todo: [], in_progress: [], completed: [], cancelled: [] };
    filteredRootTasks.value.forEach((task: any) => {
        if (grouped[task.status]) {
            grouped[task.status].push(task);
        }
    });
    // Sort each group
    Object.keys(grouped).forEach((key) => {
        grouped[key].sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0));
    });
    return grouped;
});

const subtaskCountMap = computed<Record<string | number, number>>(() => {
    const map: Record<string | number, number> = {};
    (props.project.tasks || []).forEach((task: any) => {
        if (task.parent_task_id != null) {
            map[task.parent_task_id] = (map[task.parent_task_id] || 0) + 1;
        }
    });
    return map;
});

// Priority — dot + label, identical across list, kanban, sidebar, and index
const getPriorityMeta = (priority: string) => {
    const map: Record<string, { text: string; dot: string }> = {
        urgent: { text: 'text-destructive', dot: 'bg-destructive' },
        high: { text: 'text-warning', dot: 'bg-warning' },
        medium: { text: 'text-warning', dot: 'bg-warning' },
        low: { text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' },
    };
    return map[priority] || { text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' };
};

const formatDueDate = (date: string | null) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
};

const getDueDateClass = (task: any) => {
    if (!task.due_date || task.status === 'completed') return 'text-muted-foreground';
    const due = new Date(task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'text-destructive';
    if (diffDays <= 3) return 'text-warning';
    return 'text-muted-foreground';
};

// Load-more: show CARDS_PER_BATCH initially, reveal more on click
const CARDS_PER_BATCH = 8;
const visibleCount = ref<Record<string, number>>({
    todo: CARDS_PER_BATCH,
    in_progress: CARDS_PER_BATCH,
    completed: CARDS_PER_BATCH,
    cancelled: CARDS_PER_BATCH,
});

// Reset visible counts when filters change
watch([() => props.searchQuery, () => props.filterPriority], () => {
    visibleCount.value = {
        todo: CARDS_PER_BATCH,
        in_progress: CARDS_PER_BATCH,
        completed: CARDS_PER_BATCH,
        cancelled: CARDS_PER_BATCH,
    };
});

const visibleTasks = (statusKey: string) => {
    const tasks = tasksByStatus.value[statusKey] || [];
    return tasks.slice(0, visibleCount.value[statusKey] || CARDS_PER_BATCH);
};

const hasMore = (statusKey: string) => {
    const tasks = tasksByStatus.value[statusKey] || [];
    return tasks.length > (visibleCount.value[statusKey] || CARDS_PER_BATCH);
};

const remainingCount = (statusKey: string) => {
    const tasks = tasksByStatus.value[statusKey] || [];
    return Math.max(0, tasks.length - (visibleCount.value[statusKey] || CARDS_PER_BATCH));
};

const loadMore = (statusKey: string) => {
    visibleCount.value[statusKey] = (visibleCount.value[statusKey] || CARDS_PER_BATCH) + CARDS_PER_BATCH;
};
</script>

<template>
    <!-- Board-level empty states -->
    <EmptyState
        v-if="filteredRootTasks.length === 0 && hasRootTasks"
        :icon="Columns3"
        title="No matching tasks"
        description="No tasks match your current search or filters."
    />
    <EmptyState
        v-else-if="filteredRootTasks.length === 0"
        :icon="Columns3"
        title="No tasks yet"
        description="Create your first task to see it on the board."
    >
        <template #action>
            <Button size="sm" @click="$emit('add-task')">
                <Plus class="size-4" />
                Add task
            </Button>
        </template>
    </EmptyState>

    <div v-else class="flex gap-4 overflow-x-auto pb-4">
        <div v-for="col in columns" :key="col.key" class="border-border bg-muted/30 min-w-[280px] flex-1 rounded-lg border p-2.5">
            <!-- Column Header -->
            <div class="mb-3 flex items-center justify-between px-1 pt-1">
                <div class="flex items-center gap-2">
                    <span class="size-1.5 rounded-full" :class="col.dotClass"></span>
                    <span class="text-muted-foreground text-xs font-medium">{{ col.label }}</span>
                    <span class="text-muted-foreground text-xs tabular-nums">
                        {{ tasksByStatus[col.key]?.length || 0 }}
                    </span>
                </div>
            </div>

            <!-- Task Cards -->
            <div class="max-h-[calc(100vh-280px)] space-y-2 overflow-y-auto pr-1">
                <div
                    v-for="task in visibleTasks(col.key)"
                    :key="task.id"
                    role="button"
                    tabindex="0"
                    class="border-border bg-card hover:border-muted-foreground/30 focus-visible:ring-ring/50 cursor-pointer rounded-lg border p-3 shadow-xs transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                    @click="$emit('view-task', task)"
                    @keydown.enter="$emit('view-task', task)"
                >
                    <!-- Top row: completion toggle + title + menu -->
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div class="flex min-w-0 flex-1 items-start gap-2">
                            <button
                                type="button"
                                :aria-label="task.status === 'completed' ? 'Mark task as not completed' : 'Mark task as completed'"
                                @click.stop="$emit('toggle-task', task)"
                                :disabled="updatingTasks.has(task.id)"
                                class="focus-visible:ring-ring/50 mt-0.5 flex size-5 flex-shrink-0 cursor-pointer items-center justify-center rounded-full border-2 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                :class="
                                    task.status === 'completed'
                                        ? 'border-success bg-success text-success-foreground'
                                        : 'border-muted-foreground/40 hover:border-primary text-transparent'
                                "
                            >
                                <Loader2 v-if="updatingTasks.has(task.id)" class="text-muted-foreground size-3 animate-spin" />
                                <CheckCircle2 v-else-if="task.status === 'completed'" class="size-3.5" />
                                <Circle v-else class="size-3" />
                            </button>
                            <span
                                class="line-clamp-2 text-sm leading-tight font-medium"
                                :class="task.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground'"
                                :title="task.title"
                            >
                                {{ task.title }}
                            </span>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" aria-label="Task actions" class="size-7 flex-shrink-0" @click.stop>
                                    <MoreVertical class="text-muted-foreground size-3.5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                                <DropdownMenuItem @click.stop="$emit('view-task', task)">
                                    <Eye class="size-4" />
                                    View details
                                </DropdownMenuItem>
                                <DropdownMenuItem @click.stop="$emit('edit-task', task)">
                                    <Edit class="size-4" />
                                    Edit task
                                </DropdownMenuItem>
                                <DropdownMenuItem @click.stop="$emit('delete-task', task)" class="text-destructive focus:text-destructive">
                                    <Trash2 class="size-4" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <!-- Description -->
                    <p v-if="task.description" class="text-muted-foreground mb-2 line-clamp-2 text-xs">
                        {{ task.description }}
                    </p>

                    <!-- Bottom row: meta info -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Priority -->
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="getPriorityMeta(task.priority).text">
                            <span class="size-1.5 rounded-full" :class="getPriorityMeta(task.priority).dot"></span>
                            {{ task.priority.charAt(0).toUpperCase() + task.priority.slice(1) }}
                        </span>

                        <!-- Due date -->
                        <span v-if="task.due_date" class="inline-flex items-center gap-1 text-xs tabular-nums" :class="getDueDateClass(task)">
                            <CalendarDays class="size-3" />
                            {{ formatDueDate(task.due_date) }}
                        </span>

                        <!-- Subtask count -->
                        <span v-if="subtaskCountMap[task.id]" class="text-muted-foreground text-xs tabular-nums">
                            {{ subtaskCountMap[task.id] }} sub
                        </span>
                    </div>

                    <!-- Tags -->
                    <div v-if="task.tags && task.tags.length > 0" class="mt-2 flex flex-wrap gap-1">
                        <span
                            v-for="tag in task.tags.slice(0, 3)"
                            :key="tag.id"
                            class="inline-flex h-5 items-center rounded-md border px-1.5 text-xs font-medium"
                            :style="`border-color: ${tag.color}66; color: ${tag.color}; background-color: ${tag.color}14`"
                        >
                            {{ tag.name }}
                        </span>
                        <button
                            v-if="task.tags.length > 3"
                            type="button"
                            :aria-label="`Show all ${task.tags.length} tags`"
                            class="border-border bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground focus-visible:ring-ring/50 inline-flex h-5 cursor-pointer items-center rounded-md border px-1.5 text-xs tabular-nums transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                            @click.stop="$emit('view-tags', task)"
                        >
                            +{{ task.tags.length - 3 }}
                        </button>
                    </div>
                </div>

                <!-- Load More -->
                <button
                    v-if="hasMore(col.key)"
                    type="button"
                    @click="loadMore(col.key)"
                    class="border-input text-muted-foreground hover:border-primary/40 hover:bg-card hover:text-foreground focus-visible:ring-ring/50 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-md border border-dashed py-2 text-xs transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <ChevronsDown class="size-3.5" />
                    Show more ({{ remainingCount(col.key) }} remaining)
                </button>

                <!-- Empty column -->
                <div v-if="!tasksByStatus[col.key]?.length" class="border-border rounded-md border border-dashed py-8 text-center">
                    <p class="text-muted-foreground text-xs">No tasks</p>
                </div>
            </div>
        </div>
    </div>
</template>
