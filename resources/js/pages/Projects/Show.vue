<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, watch } from 'vue';
import {
    Calendar,
    Plus,
    Edit,
    Trash2,
    Tag as TagIcon,
    CheckCircle2,
    Circle,
    PlayCircle,
    XCircle,
    ChevronDown,
    ChevronRight,
    AlertCircle
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, Project, Task } from '@/types';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { DateFormatter, type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';

interface Props {
    project: Project & {
        tasks?: ExtendedTask[];
    };
    tags: Tag[];
    completionPercentage: number;
}

interface Tag {
    id: number;
    name: string;
    slug: string;
    color: string;
    description?: string;
    user_id: number;
    created_at: string;
    updated_at: string;
}

interface ExtendedTask extends Task {
    tags?: Tag[];
    subtasks?: ExtendedTask[];
    parent_task_id?: number;
}

const props = defineProps<Props>();

// Date formatter
const df = new DateFormatter('en-US', { dateStyle: 'long' });

// Breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) }
]);

// State
const isAddTaskModalOpen = ref(false);
const isEditTaskModalOpen = ref(false);
const editingTask = ref<ExtendedTask | null>(null);
const isDeleteTaskDialogOpen = ref(false);
const taskToDelete = ref<ExtendedTask | null>(null);
const expandedTasks = ref<Set<number>>(new Set());
const isSubmitting = ref(false);

// Get page data for reactivity
const page = usePage();

// Available tags from props (reactive)
const availableTags = computed(() => props.tags);

// Task form
const taskForm = reactive({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    due_date: '',
    start_date: '',
    project_id: props.project.id,
    assigned_to: null as number | null,
    parent_task_id: null as number | null,
    tag_ids: [] as number[],
    new_tags: [] as string[]
});

// Date values for form
const startDateValue = ref<DateValue>();
const dueDateValue = ref<DateValue>();

// New tag form state
const newTagForm = reactive({
    name: '',
    color: '#6B7280',
    description: ''
});
const isCreatingTag = ref(false);

// Computed
const rootTasks = computed(() => {
    return (props.project.tasks || []).filter(task => !task.parent_task_id);
});

const getSubtasks = (parentId: number) => {
    return (props.project.tasks || []).filter(task => task.parent_task_id === parentId);
};

// Status configurations
const statusConfig = {
    todo: { label: 'To Do', icon: Circle, class: 'bg-gray-100 text-gray-800' },
    in_progress: { label: 'In Progress', icon: PlayCircle, class: 'bg-blue-100 text-blue-800' },
    completed: { label: 'Completed', icon: CheckCircle2, class: 'bg-green-100 text-green-800' },
    cancelled: { label: 'Cancelled', icon: XCircle, class: 'bg-red-100 text-red-800' }
};

const priorityConfig = {
    low: { label: 'Low', class: 'bg-green-100 text-green-800' },
    medium: { label: 'Medium', class: 'bg-yellow-100 text-yellow-800' },
    high: { label: 'High', class: 'bg-orange-100 text-orange-800' },
    urgent: { label: 'Urgent', class: 'bg-red-100 text-red-800' }
};

// Methods
const toggleTaskExpansion = (taskId: number) => {
    if (expandedTasks.value.has(taskId)) {
        expandedTasks.value.delete(taskId);
    } else {
        expandedTasks.value.add(taskId);
    }
};

const openAddTaskModal = (parentTaskId?: number) => {
    resetTaskForm();
    if (parentTaskId) {
        taskForm.parent_task_id = parentTaskId;
    }
    isAddTaskModalOpen.value = true;
};

const openEditTaskModal = (task: ExtendedTask) => {
    populateTaskForm(task);
    editingTask.value = task;
    isEditTaskModalOpen.value = true;
};

const resetTaskForm = () => {
    taskForm.title = '';
    taskForm.description = '';
    taskForm.status = 'todo';
    taskForm.priority = 'medium';
    taskForm.due_date = '';
    taskForm.start_date = '';
    taskForm.assigned_to = null;
    taskForm.parent_task_id = null;
    taskForm.tag_ids = [];
    taskForm.new_tags = [];
    startDateValue.value = undefined;
    dueDateValue.value = undefined;
};

const populateTaskForm = (task: ExtendedTask) => {
    taskForm.title = task.title;
    taskForm.description = task.description || '';
    taskForm.status = task.status;
    taskForm.priority = task.priority || 'medium';
    taskForm.due_date = task.due_date || '';
    taskForm.start_date = task.start_date || '';
    taskForm.assigned_to = task.assigned_to || null;
    taskForm.parent_task_id = task.parent_task_id || null;
    taskForm.tag_ids = task.tags?.map(tag => tag.id) || [];

    // Set date values
    if (task.start_date) {
        try {
            startDateValue.value = parseDate(task.start_date.split('T')[0]);
        } catch (e) {
            startDateValue.value = undefined;
        }
    }

    if (task.due_date) {
        try {
            dueDateValue.value = parseDate(task.due_date.split('T')[0]);
        } catch (e) {
            dueDateValue.value = undefined;
        }
    }
};

const submitTaskForm = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    // Update form dates
    taskForm.start_date = startDateValue.value ? startDateValue.value.toString() : '';
    taskForm.due_date = dueDateValue.value ? dueDateValue.value.toString() : '';

    const url = editingTask.value
        ? route('tasks.update', editingTask.value.id)
        : route('tasks.store');

    const method = editingTask.value ? 'put' : 'post';

    router[method](url, taskForm, {
        preserveScroll: true,
        onSuccess: () => {
            closeTaskModals();
        },
        onError: (errors) => {
            console.error('Task submission errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

const closeTaskModals = () => {
    isAddTaskModalOpen.value = false;
    isEditTaskModalOpen.value = false;
    editingTask.value = null;
    resetTaskForm();
};

const createNewTag = () => {
    if (!newTagForm.name.trim() || isCreatingTag.value) return;

    isCreatingTag.value = true;

    // Use router.post for proper Inertia handling
    router.post(route('tags.store'), newTagForm, {
        preserveScroll: true,
        onSuccess: () => {
            // Reset new tag form
            newTagForm.name = '';
            newTagForm.color = '#6B7280';
            newTagForm.description = '';
        },
        onError: (errors) => {
            console.error('Failed to create tag:', errors);
        },
        onFinish: () => {
            isCreatingTag.value = false;
        }
    });
};

const deleteTask = (task: ExtendedTask) => {
    taskToDelete.value = task;
    isDeleteTaskDialogOpen.value = true;
};

const confirmDeleteTask = () => {
    if (!taskToDelete.value) return;

    router.delete(route('tasks.destroy', taskToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteTaskDialogOpen.value = false;
            taskToDelete.value = null;
        }
    });
};

const formatDate = (date: string | null): string => {
    if (!date) return '';
    return new Date(date).toLocaleDateString();
};

const getProjectStatusClass = (status: string): string => {
    switch (status) {
        case 'active': return 'bg-blue-100 text-blue-800';
        case 'paused': return 'bg-orange-100 text-orange-800';
        case 'completed': return 'bg-green-100 text-green-800';
        case 'archived': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getProjectPriorityClass = (priority: string): string => {
    switch (priority) {
        case 'high': return 'bg-red-100 text-red-800';
        case 'medium': return 'bg-yellow-100 text-yellow-800';
        case 'low': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

// Watch for flash messages to handle new tag creation
watch(() => page.props.flash, (flash: any) => {
    if (flash?.newTag && !taskForm.tag_ids.includes(flash.newTag.id)) {
        // Auto-select the newly created tag
        taskForm.tag_ids.push(flash.newTag.id);
    }
}, { deep: true, immediate: true });

// Lifecycle - removed onMounted since tags come from props
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />

        <div class="container mx-auto px-4 py-6">
            <!-- Project Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4">
                        <div
                            class="w-4 h-4 rounded-full border-2 border-white shadow-sm"
                            :style="`background-color: ${project.color}`"
                        ></div>
                        <h1 class="text-3xl font-bold text-gray-900">{{ project.name }}</h1>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Badge :class="getProjectStatusClass(project.status)">
                            {{ project.status.charAt(0).toUpperCase() + project.status.slice(1) }}
                        </Badge>
                        <Badge :class="getProjectPriorityClass(project.priority)">
                            {{ project.priority.charAt(0).toUpperCase() + project.priority.slice(1) }} Priority
                        </Badge>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm font-medium text-gray-600">Progress</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold">{{ completionPercentage }}%</span>
                                    <CheckCircle2 class="w-5 h-5 text-green-600" />
                                </div>
                                <Progress :value="completionPercentage" class="h-2" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm font-medium text-gray-600">Timeline</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2">
                            <div class="flex items-center text-sm">
                                <Calendar class="w-4 h-4 mr-2 text-gray-400" />
                                <span class="text-gray-600">Start:</span>
                                <span class="ml-1 font-medium">{{ formatDate(project.start_date) || 'Not set' }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <AlertCircle class="w-4 h-4 mr-2 text-gray-400" />
                                <span class="text-gray-600">Due:</span>
                                <span class="ml-1 font-medium">{{ formatDate(project.due_date) || 'Not set' }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm font-medium text-gray-600">Tasks</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ project.tasks?.length || 0 }}</div>
                            <div class="text-sm text-gray-600">
                                {{ project.tasks?.filter(t => t.status === 'completed').length || 0 }} completed
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-if="project.description" class="mt-4">
                    <p class="text-gray-600">{{ project.description }}</p>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-semibold text-gray-900">Tasks</h2>
                    <Button @click="openAddTaskModal()" class="flex items-center space-x-2">
                        <Plus class="w-4 h-4" />
                        <span>Add Task</span>
                    </Button>
                </div>

                <!-- Tasks List -->
                <div class="space-y-4">
                    <Card v-if="rootTasks.length === 0" class="text-center py-12">
                        <CardContent>
                            <div class="flex flex-col items-center space-y-4">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
                                    <CheckCircle2 class="w-8 h-8 text-gray-300" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">No tasks yet</h3>
                                    <p class="text-gray-500">Create your first task to get started</p>
                                </div>
                                <Button @click="openAddTaskModal()" variant="outline">
                                    <Plus class="w-4 h-4 mr-2" />
                                    Add First Task
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Root Tasks -->
                    <Card v-for="task in rootTasks" :key="task.id" class="overflow-hidden">
                        <CardContent class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <Button
                                            v-if="getSubtasks(task.id).length > 0"
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleTaskExpansion(task.id)"
                                            class="p-1 h-6 w-6"
                                        >
                                            <ChevronDown v-if="expandedTasks.has(task.id)" class="w-4 h-4" />
                                            <ChevronRight v-else class="w-4 h-4" />
                                        </Button>
                                        <div v-else class="w-6"></div>

                                        <component
                                            :is="statusConfig[task.status]?.icon || Circle"
                                            class="w-5 h-5"
                                            :class="task.status === 'completed' ? 'text-green-600' : 'text-gray-400'"
                                        />

                                        <h3 class="text-lg font-medium text-gray-900">{{ task.title }}</h3>
                                    </div>

                                    <div v-if="task.description" class="ml-9 mb-3">
                                        <p class="text-gray-600">{{ task.description }}</p>
                                    </div>

                                    <div class="ml-9 flex items-center space-x-4 text-sm text-gray-500">
                                        <Badge :class="statusConfig[task.status]?.class || 'bg-gray-100 text-gray-800'">
                                            {{ statusConfig[task.status]?.label || task.status }}
                                        </Badge>

                                        <Badge :class="priorityConfig[task.priority || 'medium']?.class">
                                            {{ priorityConfig[task.priority || 'medium']?.label }}
                                        </Badge>

                                        <div v-if="task.due_date" class="flex items-center">
                                            <Calendar class="w-4 h-4 mr-1" />
                                            {{ formatDate(task.due_date) }}
                                        </div>
                                    </div>

                                    <div v-if="task.tags && task.tags.length > 0" class="ml-9 mt-2 flex items-center space-x-2">
                                        <TagIcon class="w-4 h-4 text-gray-400" />
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="tag in task.tags"
                                                :key="tag.id"
                                                variant="outline"
                                                class="text-xs"
                                                :style="`border-color: ${tag.color}; color: ${tag.color}`"
                                            >
                                                {{ tag.name }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2 ml-4">
                                    <Button variant="ghost" size="sm" @click="openAddTaskModal(task.id)">
                                        <Plus class="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" @click="openEditTaskModal(task)">
                                        <Edit class="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" @click="deleteTask(task)" class="text-red-600 hover:text-red-800">
                                        <Trash2 class="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>

                            <!-- Subtasks -->
                            <div v-if="expandedTasks.has(task.id) && getSubtasks(task.id).length > 0" class="ml-9 mt-4 space-y-3">
                                <div
                                    v-for="subtask in getSubtasks(task.id)"
                                    :key="subtask.id"
                                    class="border-l-2 border-gray-200 pl-4 py-2"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-1">
                                                <component
                                                    :is="statusConfig[subtask.status]?.icon || Circle"
                                                    class="w-4 h-4"
                                                    :class="subtask.status === 'completed' ? 'text-green-600' : 'text-gray-400'"
                                                />
                                                <h4 class="font-medium text-gray-900">{{ subtask.title }}</h4>
                                            </div>

                                            <div v-if="subtask.description" class="ml-7 mb-2">
                                                <p class="text-sm text-gray-600">{{ subtask.description }}</p>
                                            </div>

                                            <div class="ml-7 flex items-center space-x-3 text-xs text-gray-500">
                                                <Badge :class="statusConfig[subtask.status]?.class || 'bg-gray-100 text-gray-800'" class="text-xs">
                                                    {{ statusConfig[subtask.status]?.label || subtask.status }}
                                                </Badge>

                                                <div v-if="subtask.due_date" class="flex items-center">
                                                    <Calendar class="w-3 h-3 mr-1" />
                                                    {{ formatDate(subtask.due_date) }}
                                                </div>
                                            </div>

                                            <div v-if="subtask.tags && subtask.tags.length > 0" class="ml-7 mt-1 flex flex-wrap gap-1">
                                                <Badge
                                                    v-for="tag in subtask.tags"
                                                    :key="tag.id"
                                                    variant="outline"
                                                    class="text-xs"
                                                    :style="`border-color: ${tag.color}; color: ${tag.color}`"
                                                >
                                                    {{ tag.name }}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-1 ml-2">
                                            <Button variant="ghost" size="sm" @click="openEditTaskModal(subtask)" class="h-6 w-6 p-0">
                                                <Edit class="w-3 h-3" />
                                            </Button>
                                            <Button variant="ghost" size="sm" @click="deleteTask(subtask)" class="h-6 w-6 p-0 text-red-600 hover:text-red-800">
                                                <Trash2 class="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Add/Edit Task Dialog -->
        <Dialog :open="isAddTaskModalOpen || isEditTaskModalOpen" @update:open="(open) => { if (!open) closeTaskModals(); }">
            <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ editingTask ? 'Edit Task' : 'Add New Task' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingTask ? 'Update the task details below.' : 'Fill in the details to create a new task.' }}
                        {{ taskForm.parent_task_id ? ' This will be created as a subtask.' : '' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitTaskForm" class="grid gap-4 py-4">
                    <!-- Task Title -->
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label for="task-title" class="text-right">Title *</Label>
                        <div class="col-span-3">
                            <Input
                                id="task-title"
                                v-model="taskForm.title"
                                placeholder="Enter task title"
                                required
                            />
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="grid grid-cols-4 items-start gap-4">
                        <Label for="task-description" class="text-right pt-2">Description</Label>
                        <div class="col-span-3">
              <Textarea
                  id="task-description"
                  v-model="taskForm.description"
                  placeholder="Enter task description"
                  rows="3"
              />
                        </div>
                    </div>

                    <!-- Status and Priority -->
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right">Status</Label>
                        <div class="col-span-1">
                            <Select v-model="taskForm.status">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="todo">To Do</SelectItem>
                                    <SelectItem value="in_progress">In Progress</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="cancelled">Cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Label class="text-right">Priority</Label>
                        <div class="col-span-1">
                            <Select v-model="taskForm.priority">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label class="text-right">Start Date</Label>
                        <div class="col-span-1">
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button variant="outline" class="w-full justify-start text-left font-normal">
                                        <Calendar class="mr-2 h-4 w-4" />
                                        {{ startDateValue ? df.format(startDateValue.toDate(getLocalTimeZone())) : "Pick date" }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <CalendarComponent v-model="startDateValue" />
                                </PopoverContent>
                            </Popover>
                        </div>

                        <Label class="text-right">Due Date</Label>
                        <div class="col-span-1">
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button variant="outline" class="w-full justify-start text-left font-normal">
                                        <Calendar class="mr-2 h-4 w-4" />
                                        {{ dueDateValue ? df.format(dueDateValue.toDate(getLocalTimeZone())) : "Pick date" }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <CalendarComponent v-model="dueDateValue" />
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>



                    <!-- Tags -->
                    <div class="grid grid-cols-4 items-start gap-4">
                        <Label class="text-right pt-2">Tags</Label>
                        <div class="col-span-3 space-y-3">
                            <!-- Selected Tags Display -->
                            <div v-if="taskForm.tag_ids.length > 0" class="flex flex-wrap gap-2 p-2 bg-gray-50 rounded-md">
                                <Badge
                                    v-for="tagId in taskForm.tag_ids"
                                    :key="tagId"
                                    variant="outline"
                                    class="cursor-pointer hover:bg-red-50"
                                    :style="`border-color: ${availableTags.find(t => t.id === tagId)?.color}; color: ${availableTags.find(t => t.id === tagId)?.color}`"
                                    @click="() => {
                    const index = taskForm.tag_ids.indexOf(tagId);
                    if (index > -1) {
                      taskForm.tag_ids.splice(index, 1);
                    }
                  }"
                                >
                                    {{ availableTags.find(t => t.id === tagId)?.name }}
                                    <span class="ml-1 text-xs">×</span>
                                </Badge>
                            </div>

                            <!-- Available Tags Selection -->
                            <div v-if="availableTags.length > 0">
                                <Label class="text-sm text-gray-600 mb-2 block">Select existing tags:</Label>
                                <div class="flex flex-wrap gap-2 max-h-32 overflow-y-auto p-2 border rounded-md">
                                    <Badge
                                        v-for="tag in availableTags.filter(t => !taskForm.tag_ids.includes(t.id))"
                                        :key="tag.id"
                                        variant="outline"
                                        class="cursor-pointer transition-colors hover:bg-gray-50"
                                        @click="() => {
                      if (!taskForm.tag_ids.includes(tag.id)) {
                        taskForm.tag_ids.push(tag.id);
                      }
                    }"
                                    >
                                        <span class="mr-1">+</span>
                                        {{ tag.name }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Create New Tag -->
                            <div class="border-t pt-3 space-y-2">
                                <Label class="text-sm font-medium text-gray-600">Create New Tag</Label>
                                <div class="flex items-center space-x-2">
                                    <Input
                                        v-model="newTagForm.name"
                                        placeholder="Enter new tag name"
                                        class="flex-1"
                                    />
                                    <Input
                                        v-model="newTagForm.color"
                                        type="color"
                                        class="w-12 h-9"
                                        title="Tag color"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="createNewTag"
                                        :disabled="!newTagForm.name.trim() || isCreatingTag"
                                    >
                                        {{ isCreatingTag ? 'Creating...' : 'Add Tag' }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeTaskModals" :disabled="isSubmitting">
                        Cancel
                    </Button>
                    <Button type="button" @click="submitTaskForm" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : (editingTask ? 'Update Task' : 'Create Task') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Task Confirmation Dialog -->
        <AlertDialog :open="isDeleteTaskDialogOpen" @update:open="isDeleteTaskDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Task</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{{ taskToDelete?.title }}"? This action cannot be undone and will permanently remove the task and all its subtasks.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="() => { isDeleteTaskDialogOpen = false; taskToDelete = null; }">
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        @click="confirmDeleteTask"
                        class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                    >
                        Delete Task
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
