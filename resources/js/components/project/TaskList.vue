<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Plus,
    ChevronDown,
    MoreVertical,
    CheckCircle2,
    Trash2,
    Tag as TagIcon,
    Edit,
    ChevronUp,
    ChevronsUp,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';

const TASKS_PER_PAGE = 10;

const props = defineProps({
    project: {
        type: Object,
        required: true
    },
    updatingTasks: {
        type: Object,
        required: true
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
    'reorder-tasks'
]);

// Computed - filter + group tasks by status
const filteredRootTasks = computed(() => {
    let tasks = (props.project.tasks || []).filter((task) => !task.parent_task_id);

    if (props.searchQuery) {
        const q = props.searchQuery.toLowerCase();
        tasks = tasks.filter((task) =>
            task.title.toLowerCase().includes(q) ||
            (task.description && task.description.toLowerCase().includes(q))
        );
    }

    if (props.filterPriority && props.filterPriority !== 'all') {
        tasks = tasks.filter((task) => task.priority === props.filterPriority);
    }

    return tasks;
});

const tasksByStatus = computed(() => {
    return {
        todo: filteredRootTasks.value.filter((task) => task.status === 'todo').sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)),
        in_progress: filteredRootTasks.value.filter((task) => task.status === 'in_progress').sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)),
        completed: filteredRootTasks.value.filter((task) => task.status === 'completed').sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)),
        cancelled: filteredRootTasks.value.filter((task) => task.status === 'cancelled').sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0)),
    };
});

// Pagination state per status
const currentPage = ref<Record<string, number>>({ todo: 1, in_progress: 1, completed: 1, cancelled: 1 });

// Reset pages when filters change
watch([() => props.searchQuery, () => props.filterPriority], () => {
    currentPage.value = { todo: 1, in_progress: 1, completed: 1, cancelled: 1 };
});

const paginatedTasks = (statusKey: string) => {
    const tasks = tasksByStatus.value[statusKey] || [];
    const page = currentPage.value[statusKey] || 1;
    const start = (page - 1) * TASKS_PER_PAGE;
    return tasks.slice(start, start + TASKS_PER_PAGE);
};

const totalPages = (statusKey: string) => {
    const tasks = tasksByStatus.value[statusKey] || [];
    return Math.max(1, Math.ceil(tasks.length / TASKS_PER_PAGE));
};

const goToPage = (statusKey: string, page: number) => {
    currentPage.value[statusKey] = Math.max(1, Math.min(page, totalPages(statusKey)));
};

const subtaskCountMap = computed<Record<string | number, number>>(() => {
    const map: Record<string | number, number> = {};
    const allTasks = props.project.tasks || [];

    allTasks.forEach(task => {
        if (task.parent_task_id != null) {
            if (!map[task.parent_task_id]) {
                map[task.parent_task_id] = 0;
            }
            map[task.parent_task_id]++;
        }
    });

    return map;
});

// Status section configuration
const statusSections = computed(() => {
    return [
        {
            key: 'todo',
            label: 'Not Started',
            bgColor: 'bg-amber-100',
            textColor: 'text-amber-800',
            tasks: tasksByStatus.value.todo || [],
        },
        {
            key: 'in_progress',
            label: 'In Progress',
            bgColor: 'bg-amber-100',
            textColor: 'text-amber-800',
            tasks: tasksByStatus.value.in_progress || [],
        },
        {
            key: 'completed',
            label: 'Completed',
            bgColor: 'bg-green-100',
            textColor: 'text-green-800',
            tasks: tasksByStatus.value.completed || [],
        },
        {
            key: 'cancelled',
            label: 'Cancelled',
            bgColor: 'bg-red-100',
            textColor: 'text-red-800',
            tasks: tasksByStatus.value.cancelled || [],
        },
    ].filter(section => section.tasks.length > 0);
});

// Format timeline date for new UI
const formatTimelineDate = (task) => {
    if (!task.start_date && !task.due_date) return '-';

    let result = '';
    if (task.start_date) {
        const startDate = new Date(task.start_date);
        result += startDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    if (task.due_date) {
        const dueDate = new Date(task.due_date);
        if (result) {
            result += ' - ';
        }
        result += dueDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    return result;
};

// Get priority class
const getPriorityClass = (priority) => {
    const classes = {
        'urgent': 'bg-red-100 text-red-800 border-red-200',
        'high': 'bg-red-100 text-red-800 border-red-200',
        'medium': 'bg-yellow-100 text-yellow-800 border-yellow-200',
        'low': 'bg-green-100 text-green-800 border-green-200',
    };
    return classes[priority] || 'bg-gray-100 text-gray-800 border-gray-200';
};

// Check if task has tags
const hasTaskTags = (task) => {
    return task.tags && task.tags.length > 0;
};

// Sort control methods
const moveTaskUp = (task: any, statusTasks: any[]) => {
    const currentIndex = statusTasks.findIndex(t => t.id === task.id);

    if (currentIndex <= 0) return; // Already at top

    const updates = [
        { id: task.id, sort_order: statusTasks[currentIndex - 1].sort_order },
        { id: statusTasks[currentIndex - 1].id, sort_order: task.sort_order }
    ];

    emit('reorder-tasks', updates);
};

const moveTaskDown = (task: any, statusTasks: any[]) => {
    const currentIndex = statusTasks.findIndex(t => t.id === task.id);

    if (currentIndex >= statusTasks.length - 1) return; // Already at bottom

    const updates = [
        { id: task.id, sort_order: statusTasks[currentIndex + 1].sort_order },
        { id: statusTasks[currentIndex + 1].id, sort_order: task.sort_order }
    ];

    emit('reorder-tasks', updates);
};

const moveTaskToTop = (task: any, statusTasks: any[]) => {
    const currentIndex = statusTasks.findIndex(t => t.id === task.id);

    if (currentIndex <= 0) return; // Already at top

    // Create updates to shift all tasks and move current to top
    const updates = statusTasks.slice(0, currentIndex).map((t, index) => ({
        id: t.id,
        sort_order: index + 1
    }));

    updates.push({ id: task.id, sort_order: 0 });

    emit('reorder-tasks', updates);
};

// Check if task can move up/down
const canMoveUp = (task: any, statusTasks: any[]) => {
    const currentIndex = statusTasks.findIndex(t => t.id === task.id);
    return currentIndex > 0;
};

const canMoveDown = (task: any, statusTasks: any[]) => {
    const currentIndex = statusTasks.findIndex(t => t.id === task.id);
    return currentIndex < statusTasks.length - 1;
};
</script>

<template>
    <div class="space-y-4">
        <!-- Tasks by Status -->
        <div class="space-y-4">
            <div v-for="status in statusSections" :key="status.key"
                 class="rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center space-x-2">
                        <div
                            class="h-6 w-6 rounded-md"
                            :class="status.bgColor">
                        </div>
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{
                                status.label
                            }}</span>
                        <Badge
                            class="bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                        >{{ status.tasks.length }}</Badge
                        >
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12"></TableHead>
                            <TableHead class="w-48">Task Name</TableHead>
                            <TableHead class="w-64">Description</TableHead>
                            <TableHead class="w-24">Subtasks</TableHead>
                            <TableHead class="w-40">Timeline Date</TableHead>
                            <TableHead class="w-32">Priority</TableHead>
                            <TableHead class="w-48">Tags</TableHead>
                            <TableHead class="w-16">Order</TableHead>
                            <TableHead class="w-28 text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="task in paginatedTasks(status.key)"
                            :key="task.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <TableCell class="w-8">
                                <Checkbox
                                    :checked="task.status === 'completed'"
                                    @click="$emit('toggle-task', task)"
                                    :disabled="updatingTasks.has(task.id)"
                                />
                            </TableCell>
                            <TableCell
                                class="font-medium"
                                :class="{'line-through text-gray-500 dark:text-gray-400': task.status === 'completed'}">
                                {{ task.title }}
                                <span
                                    v-if="subtaskCountMap[task.id]"
                                    class="ml-2 text-xs text-gray-400 dark:text-gray-500">
                                        ({{ subtaskCountMap[task.id] }} subtask{{subtaskCountMap[task.id] > 1 ? 's' : '' }})</span>
                            </TableCell>
                            <TableCell class="text-gray-500 dark:text-gray-400 max-w-md">
                                      <span class="line-clamp-1">{{
                                              task.description || 'No description provided.' }}</span>
                            </TableCell>
                            <TableCell>
                                      <span
                                          v-if="subtaskCountMap[task.id]"
                                          class="text-blue-600 cursor-pointer hover:underline text-sm"
                                          @click="$emit('view-task', task)">{{ subtaskCountMap[task.id] }} tasks</span>
                                <span v-else class="text-gray-400 cursor-pointer hover:underline text-xs dark:text-gray-500"  @click="$emit('view-task', task)">0 task</span>
                            </TableCell>
                            <TableCell>
                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                    {{ formatTimelineDate(task) }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :class="getPriorityClass(task.priority)" class="px-2 py-1">
                                    {{ task.priority.charAt(0).toUpperCase() + task.priority.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div v-if="hasTaskTags(task)" class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="tag in task.tags.slice(0, 2)"
                                        :key="tag.id"
                                        variant="outline"
                                        class="px-1.5 py-0.5 text-xs"
                                        :style="`border-color: ${tag.color}; color: ${tag.color}`"
                                    >
                                        {{ tag.name }}
                                    </Badge>
                                    <Badge
                                        v-if="task.tags.length > 2"
                                        variant="outline"
                                        class="px-1.5 py-0.5 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                        @click="$emit('view-tags', task)"
                                    >
                                        +{{ task.tags.length - 2 }} more
                                    </Badge>
                                </div>
                                <span v-else class="text-xs text-gray-400 dark:text-gray-500">No tags</span>
                            </TableCell>
                            <TableCell class="w-16">
                                <div class="flex items-center justify-center space-x-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="moveTaskToTop(task, status.tasks)"
                                        :disabled="!canMoveUp(task, status.tasks)"
                                        class="h-5 w-5 p-0"
                                        title="Move to top"
                                    >
                                        <ChevronsUp class="h-3 w-3" :class="canMoveUp(task, status.tasks) ? 'text-blue-600 dark:text-blue-400' : 'text-gray-300 dark:text-gray-600'" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="moveTaskUp(task, status.tasks)"
                                        :disabled="!canMoveUp(task, status.tasks)"
                                        class="h-5 w-5 p-0"
                                        title="Move up"
                                    >
                                        <ChevronUp class="h-3 w-3" :class="canMoveUp(task, status.tasks) ? 'text-gray-600 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="moveTaskDown(task, status.tasks)"
                                        :disabled="!canMoveDown(task, status.tasks)"
                                        class="h-5 w-5 p-0"
                                        title="Move down"
                                    >
                                        <ChevronDown class="h-3 w-3" :class="canMoveDown(task, status.tasks) ? 'text-gray-600 dark:text-gray-300' : 'text-gray-300 dark:text-gray-600'" />
                                    </Button>
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end space-x-1">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-40 dark:bg-gray-800">
                                            <DropdownMenuItem @click="$emit('view-task', task)">
                                                <TagIcon class="mr-2 h-3 w-3" />
                                                <span class="text-xs">View Details</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="$emit('edit-task', task)">
                                                <Edit class="mr-2 h-3 w-3" />
                                                <span class="text-xs">Edit Task</span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="$emit('delete-task', task)"
                                                class="text-red-600 dark:text-red-400"
                                            >
                                                <Trash2 class="mr-2 h-3 w-3" />
                                                <span class="text-xs">Delete</span>
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination -->
                <div v-if="totalPages(status.key) > 1" class="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 px-3 py-2 bg-gray-50 dark:bg-gray-800">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Page {{ currentPage[status.key] }} of {{ totalPages(status.key) }}
                        ({{ status.tasks.length }} tasks)
                    </span>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 w-7 p-0"
                            :disabled="currentPage[status.key] <= 1"
                            @click="goToPage(status.key, currentPage[status.key] - 1)"
                        >
                            <ChevronLeft class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 w-7 p-0"
                            :disabled="currentPage[status.key] >= totalPages(status.key)"
                            @click="goToPage(status.key, currentPage[status.key] + 1)"
                        >
                            <ChevronRight class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- No Tasks Message -->
            <Card v-if="statusSections.length === 0" class="py-8 text-center">
                <CardContent>
                    <div class="flex flex-col items-center space-y-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                            <CheckCircle2 class="h-6 w-6 text-gray-300 dark:text-gray-400" />
                        </div>
                        <div>
                            <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">
                                No tasks yet
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Create your first task to get started
                            </p>
                        </div>
                        <Button @click="$emit('add-task')" variant="outline" class="h-8 px-3 text-xs">
                            <Plus class="mr-2 h-3 w-3" />
                            Add First Task
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Add New Task Button (Fixed at bottom) -->
        <div class="fixed bottom-10 right-10">
            <Button @click="$emit('add-task')" size="lg" class="rounded-full h-14 w-14 p-0 shadow-lg">
                <Plus class="h-6 w-6" />
            </Button>
        </div>
    </div>
</template>

<style>

#app > div > main > div.relative > div > div.flex-1.overflow-y-auto.p-4 > div.border-t.pt-4 > div.mt-5.mb-4.rounded-lg.border.bg-gray-50.p-3 > div > div.grid.grid-cols-2.gap-3 > div:nth-child(n) > button{
    width: 100%;
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.table-container {
    overflow-x: auto;
}

.table-fixed {
    table-layout: fixed;
}

.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
