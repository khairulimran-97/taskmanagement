<script setup lang="ts">
import { computed } from 'vue';
import { Plus, ChevronDown, MoreVertical, CheckCircle2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';

const props = defineProps({
    project: {
        type: Object,
        required: true
    },
    updatingTasks: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['add-task', 'view-task', 'toggle-task']);

// Computed - Group tasks by status
const tasksByStatus = computed(() => {
    const rootTasks = (props.project.tasks || []).filter((task) => !task.parent_task_id);

    return {
        todo: rootTasks.filter((task) => task.status === 'todo'),
        in_progress: rootTasks.filter((task) => task.status === 'in_progress'),
        completed: rootTasks.filter((task) => task.status === 'completed'),
        cancelled: rootTasks.filter((task) => task.status === 'cancelled'),
    };
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
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Tasks</h2>
            <Button @click="$emit('add-task')" class="flex h-8 items-center space-x-2 px-3 text-xs shadow-sm">
                <Plus class="h-3 w-3" />
                <span>Add Task</span>
            </Button>
        </div>

        <!-- Tasks by Status -->
        <div class="space-y-4">
            <!-- Status Sections -->
            <div v-for="status in statusSections" :key="status.key" class="rounded-md border border-gray-200 overflow-hidden">
                <div class="flex items-center justify-between p-3 bg-gray-50">
                    <div class="flex items-center space-x-2">
                        <div class="h-6 w-6 rounded-md" :class="status.bgColor"></div>
                        <span class="font-medium text-gray-700">{{ status.label }}</span>
                        <Badge class="bg-gray-100 text-gray-700">{{ status.tasks.length }}</Badge>
                    </div>
                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                        <ChevronDown class="h-4 w-4" />
                    </Button>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-8"></TableHead>
                            <TableHead>Task Name</TableHead>
                            <TableHead>Descriptions</TableHead>
                            <TableHead>Timeline Date</TableHead>
                            <TableHead>Priority</TableHead>
                            <TableHead class="w-10"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="task in status.tasks" :key="task.id" class="hover:bg-gray-50">
                            <TableCell class="w-8">
                                <Checkbox :checked="task.status === 'completed'"
                                          @click="$emit('toggle-task', task)"
                                          :disabled="updatingTasks.has(task.id)" />
                            </TableCell>
                            <TableCell class="font-medium" :class="{'line-through text-gray-500': task.status === 'completed'}">
                                {{ task.title }}
                            </TableCell>
                            <TableCell class="text-gray-500 max-w-md">
                                <span class="line-clamp-1">{{ task.description || 'No description provided.' }}</span>
                            </TableCell>
                            <TableCell>
                                <div class="text-xs text-gray-600">
                                    {{ formatTimelineDate(task) }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :class="getPriorityClass(task.priority)" class="px-2 py-1">
                                    {{ task.priority.charAt(0).toUpperCase() + task.priority.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="$emit('view-task', task)">
                                    <MoreVertical class="h-4 w-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- No Tasks Message -->
            <Card v-if="statusSections.length === 0" class="py-8 text-center">
                <CardContent>
                    <div class="flex flex-col items-center space-y-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                            <CheckCircle2 class="h-6 w-6 text-gray-300" />
                        </div>
                        <div>
                            <h3 class="text-base font-medium text-gray-900">No tasks yet</h3>
                            <p class="text-sm text-gray-500">Create your first task to get started</p>
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
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
