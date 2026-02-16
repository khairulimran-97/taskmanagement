<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    CheckCircle2,
    Circle,
    Clock,
    XCircle,
    MoreVertical,
    Trash2,
    Edit,
    Eye,
    Tag as TagIcon,
    CalendarDays,
    Loader2,
    ChevronsDown,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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

const emit = defineEmits([
    'add-task',
    'view-task',
    'toggle-task',
    'delete-task',
    'view-tags',
    'edit-task',
]);

const columns = [
    { key: 'todo', label: 'To Do', icon: Circle, iconClass: 'text-slate-400', bgClass: 'bg-slate-50 dark:bg-slate-800/50', borderClass: 'border-slate-200 dark:border-slate-700' },
    { key: 'in_progress', label: 'In Progress', icon: Clock, iconClass: 'text-blue-500', bgClass: 'bg-blue-50 dark:bg-blue-900/20', borderClass: 'border-blue-200 dark:border-blue-800' },
    { key: 'completed', label: 'Completed', icon: CheckCircle2, iconClass: 'text-green-500', bgClass: 'bg-green-50 dark:bg-green-900/20', borderClass: 'border-green-200 dark:border-green-800' },
    { key: 'cancelled', label: 'Cancelled', icon: XCircle, iconClass: 'text-red-500', bgClass: 'bg-red-50 dark:bg-red-900/20', borderClass: 'border-red-200 dark:border-red-800' },
];

const filteredRootTasks = computed(() => {
    let tasks = (props.project.tasks || []).filter((t: any) => !t.parent_task_id);

    if (props.searchQuery) {
        const q = props.searchQuery.toLowerCase();
        tasks = tasks.filter((t: any) =>
            t.title.toLowerCase().includes(q) ||
            (t.description && t.description.toLowerCase().includes(q))
        );
    }

    if (props.filterPriority && props.filterPriority !== 'all') {
        tasks = tasks.filter((t: any) => t.priority === props.filterPriority);
    }

    return tasks;
});

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

const getPriorityClass = (priority: string) => {
    const classes: Record<string, string> = {
        urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        high: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        medium: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        low: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    };
    return classes[priority] || 'bg-gray-100 text-gray-700';
};

const getPriorityDot = (priority: string) => {
    const classes: Record<string, string> = {
        urgent: 'bg-red-500',
        high: 'bg-orange-500',
        medium: 'bg-yellow-500',
        low: 'bg-green-500',
    };
    return classes[priority] || 'bg-gray-500';
};

const formatDueDate = (date: string | null) => {
    if (!date) return null;
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
};

const getDueDateClass = (task: any) => {
    if (!task.due_date || task.status === 'completed') return 'text-gray-500 dark:text-gray-400';
    const due = new Date(task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'text-red-600 dark:text-red-400';
    if (diffDays <= 1) return 'text-orange-600 dark:text-orange-400';
    if (diffDays <= 3) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-gray-500 dark:text-gray-400';
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
    <div class="flex gap-4 overflow-x-auto pb-4">
        <div
            v-for="col in columns"
            :key="col.key"
            class="min-w-[280px] flex-1 rounded-lg border p-3"
            :class="[col.bgClass, col.borderClass]"
        >
            <!-- Column Header -->
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <component :is="col.icon" class="h-4 w-4" :class="col.iconClass" />
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ col.label }}</span>
                    <Badge variant="secondary" class="h-5 px-1.5 text-xs">
                        {{ tasksByStatus[col.key]?.length || 0 }}
                    </Badge>
                </div>
            </div>

            <!-- Task Cards -->
            <div class="space-y-2 max-h-[calc(100vh-280px)] overflow-y-auto pr-1">
                <Card
                    v-for="task in visibleTasks(col.key)"
                    :key="task.id"
                    class="cursor-pointer border border-gray-200 shadow-sm transition-shadow hover:shadow-md dark:border-gray-700"
                    @click="$emit('view-task', task)"
                >
                    <CardContent class="p-3">
                        <!-- Top row: priority dot + title + menu -->
                        <div class="mb-2 flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <button
                                    @click.stop="$emit('toggle-task', task)"
                                    :disabled="updatingTasks.has(task.id)"
                                    class="mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full transition-all hover:scale-110"
                                    :class="task.status === 'completed'
                                        ? 'bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800'
                                        : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600'"
                                >
                                    <Loader2 v-if="updatingTasks.has(task.id)" class="h-3 w-3 animate-spin text-gray-400" />
                                    <CheckCircle2 v-else-if="task.status === 'completed'" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                    <Circle v-else class="h-3 w-3 text-gray-400 dark:text-gray-500" />
                                </button>
                                <span
                                    class="text-sm font-medium leading-tight"
                                    :class="task.status === 'completed' ? 'text-gray-400 line-through dark:text-gray-500' : 'text-gray-900 dark:text-gray-100'"
                                >
                                    {{ task.title }}
                                </span>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="sm" class="h-6 w-6 flex-shrink-0 p-0" @click.stop>
                                        <MoreVertical class="h-3.5 w-3.5 text-gray-400" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-36 dark:bg-gray-800">
                                    <DropdownMenuItem @click.stop="$emit('view-task', task)">
                                        <Eye class="mr-2 h-3 w-3" />
                                        <span class="text-xs">View</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click.stop="$emit('edit-task', task)">
                                        <Edit class="mr-2 h-3 w-3" />
                                        <span class="text-xs">Edit</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click.stop="$emit('delete-task', task)" class="text-red-600 dark:text-red-400">
                                        <Trash2 class="mr-2 h-3 w-3" />
                                        <span class="text-xs">Delete</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <!-- Description -->
                        <p v-if="task.description" class="mb-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ task.description }}
                        </p>

                        <!-- Bottom row: meta info -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Priority -->
                            <Badge :class="getPriorityClass(task.priority)" class="h-5 px-1.5 text-xs">
                                {{ task.priority.charAt(0).toUpperCase() + task.priority.slice(1) }}
                            </Badge>

                            <!-- Due date -->
                            <div v-if="task.due_date" class="flex items-center gap-1 text-xs" :class="getDueDateClass(task)">
                                <CalendarDays class="h-3 w-3" />
                                {{ formatDueDate(task.due_date) }}
                            </div>

                            <!-- Subtask count -->
                            <span v-if="subtaskCountMap[task.id]" class="text-xs text-gray-400 dark:text-gray-500">
                                {{ subtaskCountMap[task.id] }} sub
                            </span>
                        </div>

                        <!-- Tags -->
                        <div v-if="task.tags && task.tags.length > 0" class="mt-2 flex flex-wrap gap-1">
                            <Badge
                                v-for="tag in task.tags.slice(0, 3)"
                                :key="tag.id"
                                variant="outline"
                                class="h-5 px-1.5 text-xs"
                                :style="`border-color: ${tag.color}; color: ${tag.color}`"
                            >
                                {{ tag.name }}
                            </Badge>
                            <Badge
                                v-if="task.tags.length > 3"
                                variant="outline"
                                class="h-5 cursor-pointer px-1.5 text-xs hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click.stop="$emit('view-tags', task)"
                            >
                                +{{ task.tags.length - 3 }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Load More -->
                <button
                    v-if="hasMore(col.key)"
                    @click="loadMore(col.key)"
                    class="flex w-full items-center justify-center gap-1.5 rounded-md border border-dashed border-gray-300 py-2 text-xs text-gray-500 transition-colors hover:border-gray-400 hover:bg-white hover:text-gray-700 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                >
                    <ChevronsDown class="h-3.5 w-3.5" />
                    Show more ({{ remainingCount(col.key) }} remaining)
                </button>

                <!-- Empty column -->
                <div v-if="!tasksByStatus[col.key]?.length" class="py-8 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-500">No tasks</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
