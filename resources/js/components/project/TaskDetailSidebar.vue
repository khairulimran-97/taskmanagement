<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    X,
    TagIcon,
    CheckCircle2,
    Circle,
    Edit,
    Trash2,
    Plus,
    Loader2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
    selectedTask: {
        type: Object,
        default: null,
    },
    statusConfig: {
        type: Object,
        required: true,
    },
    priorityConfig: {
        type: Object,
        required: true,
    },
    updatingTasks: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits([
    'close',
    'toggle-task',
    'edit-subtask',
    'delete-task',
    'add-subtask',
]);

// Get subtasks for a given parent task
const getSubtasks = (parentId: string | number) => {
    return (props.project.tasks || []).filter(
        (task) => task.parent_task_id === parentId,
    );
};

const formatDate = (date: string | Date | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString();
};

const getDueDateClass = (task: any) => {
    if (!task.due_date || task.status === 'completed') return 'text-gray-500 dark:text-gray-400';

    const due = new Date(task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return 'text-red-600 font-medium dark:text-red-400'; // Overdue
    if (diffDays <= 1) return 'text-orange-600 font-medium dark:text-orange-400'; // Due soon
    if (diffDays <= 3) return 'text-yellow-600 dark:text-yellow-400'; // Due in a few days
    return 'text-gray-500 dark:text-gray-400';
};
</script>

<template>
    <div
        v-if="isOpen && selectedTask"
        class="fixed inset-y-0 right-0 z-50 w-full overflow-auto transform bg-white shadow-2xl transition-transform duration-300 ease-in-out md:w-2xl lg:w-2xl dark:bg-gray-900"
        :class="isOpen ? 'translate-x-0' : 'translate-x-full'"
    >
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center space-x-3">
                <div class="h-3 w-3 rounded-full" :style="`background-color: ${project.color}`"></div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Task Details</h2>
            </div>
            <Button variant="ghost" size="sm" @click="$emit('close')">
                <X class="h-4 w-4 text-gray-700 dark:text-gray-300" />
            </Button>
        </div>

        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-4 text-gray-900 dark:text-gray-100">
            <!-- Task Info -->
            <div class="mb-6 space-y-3">
                <div>
                    <h3 class="mb-2 text-base font-medium">{{ selectedTask.title }}</h3>
                    <p v-if="selectedTask.description" class="text-sm text-gray-600 dark:text-gray-400">
                        {{ selectedTask.description }}
                    </p>
                </div>

                <div class="flex items-center space-x-3">
                    <Badge
                        :class="statusConfig[selectedTask.status]?.class || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                        class="px-2 py-1 text-xs"
                    >
                        {{ statusConfig[selectedTask.status]?.label || selectedTask.status }}
                    </Badge>
                    <Badge
                        :class="priorityConfig[selectedTask.priority || 'medium']?.class || 'border-gray-300 dark:border-gray-600'"
                        variant="outline"
                        class="border px-2 py-1 text-xs"
                    >
                        {{ priorityConfig[selectedTask.priority || 'medium']?.label }}
                    </Badge>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Start:</span>
                        <p class="font-medium">{{ formatDate(selectedTask.start_date) }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Due:</span>
                        <p class="font-medium" :class="getDueDateClass(selectedTask)">{{ formatDate(selectedTask.due_date) }}</p>
                    </div>
                </div>

                <!-- Tags -->
                <div v-if="selectedTask.tags && selectedTask.tags.length > 0" class="flex items-center space-x-2">
                    <TagIcon class="h-3 w-3 text-gray-400 dark:text-gray-500" />
                    <div class="flex flex-wrap gap-1">
                        <Badge
                            v-for="tag in selectedTask.tags"
                            :key="tag.id"
                            variant="outline"
                            class="px-1.5 py-0.5 text-xs"
                            :style="`border-color: ${tag.color}; color: ${tag.color}`"
                        >
                            {{ tag.name }}
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Subtasks Section -->
            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-base font-medium">Subtasks ({{ getSubtasks(selectedTask.id).length }})</h4>
                    <Button size="sm" @click="$emit('add-subtask', selectedTask)" class="h-7 px-2 text-xs">
                        <Plus class="mr-1 h-3 w-3" />
                        Add
                    </Button>
                </div>

                <!-- Subtask Form Slot -->
                <slot name="subtask-form"></slot>

                <!-- Subtasks Table -->
                <div v-if="getSubtasks(selectedTask.id).length > 0" class="rounded-lg border border-gray-200 dark:border-gray-700">
                    <Table>
                        <TableHeader>
                            <TableRow class="h-9 bg-gray-100 dark:bg-gray-800">
                                <TableHead class="w-12 py-2"></TableHead>
                                <TableHead class="w-64 py-2 text-xs">Title</TableHead>
                                <TableHead class="w-24 py-2 text-xs">Status</TableHead>
                                <TableHead class="w-24 py-2 text-xs">Due</TableHead>
                                <TableHead class="w-20 py-2 text-right text-xs">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="subtask in getSubtasks(selectedTask.id)" :key="subtask.id" class="h-10 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <TableCell class="w-12 py-1">
                                    <button
                                        @click="$emit('toggle-task', subtask)"
                                        :disabled="updatingTasks.has(subtask.id)"
                                        class="flex h-4 w-4 items-center justify-center rounded-full transition-all duration-200 hover:scale-110"
                                        :class="[
                      subtask.status === 'completed'
                        ? 'bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800'
                        : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600',
                    ]"
                                    >
                                        <Loader2 v-if="updatingTasks.has(subtask.id)" class="h-3 w-3 animate-spin text-gray-500 dark:text-gray-400" />
                                        <CheckCircle2 v-else-if="subtask.status === 'completed'" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <Circle v-else class="h-3 w-3 text-gray-400 dark:text-gray-500" />
                                    </button>
                                </TableCell>
                                <TableCell class="w-64 py-1">
                                    <div>
                                        <p
                                            class="text-xs font-medium"
                                            :class="subtask.status === 'completed' ? 'text-gray-500 line-through dark:text-gray-400' : 'text-gray-900 dark:text-gray-100'"
                                        >
                                            {{ subtask.title }}
                                        </p>
                                        <p v-if="subtask.description" class="line-clamp-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ subtask.description }}
                                        </p>
                                        <div v-if="subtask.tags && subtask.tags.length > 0" class="mt-1 flex flex-wrap gap-1">
                                            <Badge
                                                v-for="tag in subtask.tags"
                                                :key="tag.id"
                                                variant="outline"
                                                class="px-1 py-0 text-xs"
                                                :style="`border-color: ${tag.color}; color: ${tag.color}`"
                                            >
                                                {{ tag.name }}
                                            </Badge>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="w-24 py-1">
                                    <Badge
                                        :class="statusConfig[subtask.status]?.class || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                                        class="px-1.5 py-0.5 text-xs"
                                    >
                                        {{ statusConfig[subtask.status]?.label || subtask.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="w-24 py-1">
                                    <div v-if="subtask.due_date" class="text-xs" :class="getDueDateClass(subtask)">
                                        {{ formatDate(subtask.due_date) }}
                                    </div>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                </TableCell>
                                <TableCell class="w-20 py-1 text-right">
                                    <div class="flex items-center justify-end space-x-1">
                                        <Button variant="ghost" size="sm" @click="$emit('edit-subtask', subtask)" class="h-6 w-6 p-0">
                                            <Edit class="h-3 w-3 text-gray-700 dark:text-gray-300" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="$emit('delete-task', subtask)"
                                            class="h-6 w-6 p-0 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-600"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-else-if="getSubtasks(selectedTask.id).length === 0" class="py-6 text-center text-gray-500 dark:text-gray-400">
                    <p class="text-sm">No subtasks yet</p>
                    <Button variant="outline" size="sm" @click="$emit('add-subtask', selectedTask)" class="mt-2 h-7 px-3 text-xs">
                        <Plus class="mr-1 h-3 w-3" />
                        Add First Subtask
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
