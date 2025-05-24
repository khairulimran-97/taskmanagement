<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Circle,
    Clock,
    Edit,
    Eye,
    Loader2,
    PlayCircle,
    Plus,
    Tag as TagIcon,
    Trash2,
    X,
    XCircle,
    ChevronDown,
    MoreVertical,
} from 'lucide-vue-next';

import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, Project, Task } from '@/types';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DateFormatter, type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';
import { Checkbox } from '@/components/ui/checkbox';

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
    { title: props.project.name, href: route('projects.show', props.project.id) },
]);

// State
const isAddTaskModalOpen = ref(false);
const isEditTaskModalOpen = ref(false);
const editingTask = ref<ExtendedTask | null>(null);
const isDeleteTaskDialogOpen = ref(false);
const taskToDelete = ref<ExtendedTask | null>(null);
const isSubmitting = ref(false);
const updatingTasks = ref<Set<number>>(new Set());

// Right side modal for task details
const isTaskDetailModalOpen = ref(false);
const selectedTask = ref<ExtendedTask | null>(null);
const isSubtaskFormOpen = ref(false);
const editingSubtask = ref<ExtendedTask | null>(null);

// Get page data for reactivity
const page = usePage();

// Available tags from props (reactive) - make it reactive so it updates when new tags are created
const availableTags = ref([...props.tags]);

// Watch for props changes to update available tags
watch(
    () => props.tags,
    (newTags) => {
        availableTags.value = [...newTags];
    },
    { deep: true },
);

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
    new_tags: [] as string[],
});

// Subtask form (separate from main task form)
const subtaskForm = reactive({
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
    new_tags: [] as string[],
});

// Date values for forms
const startDateValue = ref<DateValue>();
const dueDateValue = ref<DateValue>();
const subtaskStartDateValue = ref<DateValue>();
const subtaskDueDateValue = ref<DateValue>();

// New tag form state
const newTagForm = reactive({
    name: '',
    color: '#6B7280',
    description: '',
});
const isCreatingTag = ref(false);

// Computed - Group tasks by status (instead of priority)
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

// Get subtasks - keep this function
const getSubtasks = (parentId: number) => {
    return (props.project.tasks || []).filter((task) => task.parent_task_id === parentId);
};

// Priority configurations - keep this
const priorityConfig = {
    urgent: {
        key: 'urgent',
        label: 'Urgent Priority',
        emoji: '🔥',
        class: 'bg-red-100 text-red-800 border-red-200',
        headerClass: 'bg-red-50 border-red-200 text-red-800',
        hoverClass: 'hover:bg-red-50/50',
    },
    high: {
        key: 'high',
        label: 'High Priority',
        emoji: '⚠️',
        class: 'bg-orange-100 text-orange-800 border-orange-200',
        headerClass: 'bg-orange-50 border-orange-200 text-orange-800',
        hoverClass: 'hover:bg-orange-50/50',
    },
    medium: {
        key: 'medium',
        label: 'Medium Priority',
        emoji: '📋',
        class: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        headerClass: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        hoverClass: 'hover:bg-yellow-50/50',
    },
    low: {
        key: 'low',
        label: 'Low Priority',
        emoji: '📝',
        class: 'bg-green-100 text-green-800 border-green-200',
        headerClass: 'bg-green-50 border-green-200 text-green-800',
        hoverClass: 'hover:bg-green-50/50',
    },
};

// Status configurations - keep this
const statusConfig = {
    todo: {
        label: 'To Do',
        icon: Circle,
        class: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
        iconClass: 'text-slate-400 hover:text-slate-600',
    },
    in_progress: {
        label: 'In Progress',
        icon: PlayCircle,
        class: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
        iconClass: 'text-blue-500 hover:text-blue-700',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle2,
        class: 'bg-green-100 text-green-800 hover:bg-green-200',
        iconClass: 'text-green-500 hover:text-green-700',
    },
    cancelled: {
        label: 'Cancelled',
        icon: XCircle,
        class: 'bg-red-100 text-red-800 hover:bg-red-200',
        iconClass: 'text-red-500 hover:text-red-700',
    },
};

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

// Methods
const openAddTaskModal = () => {
    resetTaskForm();
    isAddTaskModalOpen.value = true;
};

const openEditTaskModal = (task: ExtendedTask) => {
    populateTaskForm(task);
    editingTask.value = task;
    isEditTaskModalOpen.value = true;
};

const openTaskDetailModal = (task: ExtendedTask) => {
    selectedTask.value = task;
    isTaskDetailModalOpen.value = true;
    isSubtaskFormOpen.value = false;
    editingSubtask.value = null;
    resetSubtaskForm();
};

const closeTaskDetailModal = () => {
    isTaskDetailModalOpen.value = false;
    selectedTask.value = null;
    isSubtaskFormOpen.value = false;
    editingSubtask.value = null;
    resetSubtaskForm();
};

const openSubtaskForm = (parentTask?: ExtendedTask) => {
    resetSubtaskForm();
    if (parentTask) {
        subtaskForm.parent_task_id = parentTask.id;
    }
    isSubtaskFormOpen.value = true;
    editingSubtask.value = null;
};

const editSubtask = (subtask: ExtendedTask) => {
    populateSubtaskForm(subtask);
    editingSubtask.value = subtask;
    isSubtaskFormOpen.value = true;
};

// Create new tag method
const createNewTag = () => {
    if (!newTagForm.name.trim() || isCreatingTag.value) return;

    isCreatingTag.value = true;
    const tagName = newTagForm.name.trim();

    router.post(
        route('tags.store'),
        {
            name: tagName,
            color: newTagForm.color,
            description: newTagForm.description,
        },
        {
            preserveScroll: true,
            onSuccess: (page) => {
                // Check if the new tag was returned in the session flash data
                if (page.props.flash && page.props.flash.newTag) {
                    const newTag = page.props.flash.newTag;
                    availableTags.value.push(newTag);
                    subtaskForm.tag_ids.push(newTag.id);
                } else {
                    // Fallback: create a temporary tag object for immediate UX
                    const tempTag = {
                        id: Date.now(),
                        name: tagName,
                        color: newTagForm.color,
                        description: newTagForm.description,
                        slug: tagName.toLowerCase().replace(/\s+/g, '-'),
                        user_id: page.props.auth.user.id,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    };
                    availableTags.value.push(tempTag);
                    subtaskForm.tag_ids.push(tempTag.id);
                }

                // Reset form
                newTagForm.name = '';
                newTagForm.color = '#6B7280';
                newTagForm.description = '';
            },
            onError: (errors) => {
                console.error('Failed to create tag:', errors);
            },
            onFinish: () => {
                isCreatingTag.value = false;
            },
        },
    );
};

// Quick status update function
const quickUpdateTaskStatus = (task: ExtendedTask, newStatus: string) => {
    if (updatingTasks.value.has(task.id)) return;

    updatingTasks.value.add(task.id);

    router.put(
        route('tasks.update', task.id),
        {
            status: newStatus,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                console.error('Failed to update task status:', errors);
            },
            onFinish: () => {
                updatingTasks.value.delete(task.id);
            },
        },
    );
};

// Quick toggle completion
const toggleTaskCompletion = (task: ExtendedTask) => {
    const newStatus = task.status === 'completed' ? 'todo' : 'completed';
    quickUpdateTaskStatus(task, newStatus);
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

const resetSubtaskForm = () => {
    subtaskForm.title = '';
    subtaskForm.description = '';
    subtaskForm.status = 'todo';
    subtaskForm.priority = 'medium';
    subtaskForm.due_date = '';
    subtaskForm.start_date = '';
    subtaskForm.assigned_to = null;
    subtaskForm.parent_task_id = selectedTask.value?.id || null;
    subtaskForm.tag_ids = [];
    subtaskForm.new_tags = [];
    subtaskStartDateValue.value = undefined;
    subtaskDueDateValue.value = undefined;
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
    taskForm.tag_ids = task.tags?.map((tag) => tag.id) || [];

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

const populateSubtaskForm = (task: ExtendedTask) => {
    subtaskForm.title = task.title;
    subtaskForm.description = task.description || '';
    subtaskForm.status = task.status;
    subtaskForm.priority = task.priority || 'medium';
    subtaskForm.due_date = task.due_date || '';
    subtaskForm.start_date = task.start_date || '';
    subtaskForm.assigned_to = task.assigned_to || null;
    subtaskForm.parent_task_id = task.parent_task_id || null;
    subtaskForm.tag_ids = task.tags?.map((tag) => tag.id) || [];

    // Set date values
    if (task.start_date) {
        try {
            subtaskStartDateValue.value = parseDate(task.start_date.split('T')[0]);
        } catch (e) {
            subtaskStartDateValue.value = undefined;
        }
    }

    if (task.due_date) {
        try {
            subtaskDueDateValue.value = parseDate(task.due_date.split('T')[0]);
        } catch (e) {
            subtaskDueDateValue.value = undefined;
        }
    }
};

const submitTaskForm = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    // Update form dates
    taskForm.start_date = startDateValue.value ? startDateValue.value.toString() : '';
    taskForm.due_date = dueDateValue.value ? dueDateValue.value.toString() : '';

    const url = editingTask.value ? route('tasks.update', editingTask.value.id) : route('tasks.store');

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
        },
    });
};

const submitSubtaskForm = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    // Update form dates
    subtaskForm.start_date = subtaskStartDateValue.value ? subtaskStartDateValue.value.toString() : '';
    subtaskForm.due_date = subtaskDueDateValue.value ? subtaskDueDateValue.value.toString() : '';

    const url = editingSubtask.value ? route('tasks.update', editingSubtask.value.id) : route('tasks.store');

    const method = editingSubtask.value ? 'put' : 'post';

    router[method](url, subtaskForm, {
        preserveScroll: true,
        onSuccess: () => {
            isSubtaskFormOpen.value = false;
            editingSubtask.value = null;
            resetSubtaskForm();
        },
        onError: (errors) => {
            console.error('Subtask submission errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const closeTaskModals = () => {
    isAddTaskModalOpen.value = false;
    isEditTaskModalOpen.value = false;
    editingTask.value = null;
    resetTaskForm();
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
            // Close task detail modal if deleted task was selected
            if (selectedTask.value && selectedTask.value.id === taskToDelete.value?.id) {
                closeTaskDetailModal();
            }
        },
    });
};

const formatDate = (date: string | null): string => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString();
};

const getDueDateClass = (task: ExtendedTask): string => {
    if (!task.due_date || task.status === 'completed') return 'text-gray-500';

    const due = new Date(task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return 'text-red-600 font-medium'; // Overdue
    if (diffDays <= 1) return 'text-orange-600 font-medium'; // Due soon
    if (diffDays <= 3) return 'text-yellow-600'; // Due in a few days
    return 'text-gray-500';
};

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

// Watch for date changes in subtask form
watch(subtaskStartDateValue, (newValue) => {
    if (newValue) {
        subtaskForm.start_date = newValue.toString();
    } else {
        subtaskForm.start_date = '';
    }
});

watch(subtaskDueDateValue, (newValue) => {
    if (newValue) {
        subtaskForm.due_date = newValue.toString();
    } else {
        subtaskForm.due_date = '';
    }
});

watch(startDateValue, (newValue) => {
    if (newValue) {
        taskForm.start_date = newValue.toString();
    } else {
        taskForm.start_date = '';
    }
});

watch(dueDateValue, (newValue) => {
    if (newValue) {
        taskForm.due_date = newValue.toString();
    } else {
        taskForm.due_date = '';
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />

        <div class="container mx-auto px-4 py-6">
            <!-- Project Header - Kept as-is -->
            <div class="mb-6">
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

                <!-- Project Cards Section - Kept as-is -->
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

                <div v-if="project.description" class="mt-3">
                    <p class="text-sm text-gray-600">{{ project.description }}</p>
                </div>
            </div>

            <!-- Modified Task Sections -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Tasks</h2>
                    <Button @click="openAddTaskModal()" class="flex h-8 items-center space-x-2 px-3 text-xs shadow-sm">
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
                                                  @click="toggleTaskCompletion(task)"
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
                                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0" @click="openTaskDetailModal(task)">
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
                                <Button @click="openAddTaskModal()" variant="outline" class="h-8 px-3 text-xs">
                                    <Plus class="mr-2 h-3 w-3" />
                                    Add First Task
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                
                <!-- Add New Task Button (Fixed at bottom) -->
                <div class="fixed bottom-10 right-10">
                    <Button @click="openAddTaskModal()" size="lg" class="rounded-full h-14 w-14 p-0 shadow-lg">
                        <Plus class="h-6 w-6" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Task Detail Modal -->
        <div
            v-if="isTaskDetailModalOpen && selectedTask"
            class="fixed inset-y-0 right-0 z-50 w-full transform bg-white shadow-2xl transition-transform duration-300 ease-in-out md:w-2xl lg:w-2xl"
            :class="isTaskDetailModalOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b bg-gray-50 p-4">
                <div class="flex items-center space-x-3">
                    <div class="h-3 w-3 rounded-full" :style="`background-color: ${project.color}`"></div>
                    <h2 class="text-lg font-semibold text-gray-900">Task Details</h2>
                </div>
                <Button variant="ghost" size="sm" @click="closeTaskDetailModal">
                    <X class="h-4 w-4" />
                </Button>
            </div>

            <!-- Modal Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Task Info -->
                <div class="mb-6 space-y-3">
                    <div>
                        <h3 class="mb-2 text-base font-medium text-gray-900">{{ selectedTask.title }}</h3>
                        <p v-if="selectedTask.description" class="text-sm text-gray-600">{{ selectedTask.description }}</p>
                    </div>

                    <div class="flex items-center space-x-3">
                        <Badge :class="statusConfig[selectedTask.status]?.class || 'bg-gray-100 text-gray-800'" class="px-2 py-1 text-xs">
                            {{ statusConfig[selectedTask.status]?.label || selectedTask.status }}
                        </Badge>
                        <Badge :class="priorityConfig[selectedTask.priority || 'medium']?.class" variant="outline" class="border px-2 py-1 text-xs">
                            {{ priorityConfig[selectedTask.priority || 'medium']?.label }}
                        </Badge>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-gray-500">Start:</span>
                            <p class="font-medium">{{ formatDate(selectedTask.start_date) }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Due:</span>
                            <p class="font-medium" :class="getDueDateClass(selectedTask)">{{ formatDate(selectedTask.due_date) }}</p>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="selectedTask.tags && selectedTask.tags.length > 0" class="flex items-center space-x-2">
                        <TagIcon class="h-3 w-3 text-gray-400" />
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
                <div class="border-t pt-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-base font-medium text-gray-900">Subtasks ({{ getSubtasks(selectedTask.id).length }})</h4>
                        <Button size="sm" @click="openSubtaskForm(selectedTask)" :disabled="isSubtaskFormOpen" class="h-7 px-2 text-xs">
                            <Plus class="mr-1 h-3 w-3" />
                            Add
                        </Button>
                    </div>

                    <!-- Subtasks Table -->
                    <div v-if="getSubtasks(selectedTask.id).length > 0" class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow class="h-9">
                                    <TableHead class="w-12 py-2"></TableHead>
                                    <TableHead class="w-64 py-2 text-xs">Title</TableHead>
                                    <TableHead class="w-24 py-2 text-xs">Status</TableHead>
                                    <TableHead class="w-24 py-2 text-xs">Due</TableHead>
                                    <TableHead class="w-20 py-2 text-right text-xs">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="subtask in getSubtasks(selectedTask.id)" :key="subtask.id" class="h-10">
                                    <TableCell class="w-12 py-1">
                                        <button
                                            @click="toggleTaskCompletion(subtask)"
                                            :disabled="updatingTasks.has(subtask.id)"
                                            class="flex h-4 w-4 items-center justify-center rounded-full transition-all duration-200 hover:scale-110"
                                            :class="[
                                                subtask.status === 'completed' ? 'bg-green-100 hover:bg-green-200' : 'bg-gray-100 hover:bg-gray-200',
                                            ]"
                                        >
                                            <Loader2 v-if="updatingTasks.has(subtask.id)" class="h-3 w-3 animate-spin text-gray-500" />
                                            <CheckCircle2 v-else-if="subtask.status === 'completed'" class="h-3 w-3 text-green-600" />
                                            <Circle v-else class="h-3 w-3 text-gray-400" />
                                        </button>
                                    </TableCell>
                                    <TableCell class="w-64 py-1">
                                        <div>
                                            <p
                                                class="text-xs font-medium"
                                                :class="subtask.status === 'completed' ? 'text-gray-500 line-through' : 'text-gray-900'"
                                            >
                                                {{ subtask.title }}
                                            </p>
                                            <p v-if="subtask.description" class="line-clamp-1 text-xs text-gray-500">
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
                                            :class="statusConfig[subtask.status]?.class || 'bg-gray-100 text-gray-800'"
                                            class="px-1.5 py-0.5 text-xs"
                                        >
                                            {{ statusConfig[subtask.status]?.label || subtask.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="w-24 py-1">
                                        <div v-if="subtask.due_date" class="text-xs" :class="getDueDateClass(subtask)">
                                            {{ formatDate(subtask.due_date) }}
                                        </div>
                                        <span v-else class="text-xs text-gray-400">-</span>
                                    </TableCell>
                                    <TableCell class="w-20 py-1 text-right">
                                        <div class="flex items-center justify-end space-x-1">
                                            <Button variant="ghost" size="sm" @click="editSubtask(subtask)" class="h-6 w-6 p-0">
                                                <Edit class="h-3 w-3" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="deleteTask(subtask)"
                                                class="h-6 w-6 p-0 text-red-600 hover:text-red-800"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <!-- Subtask Form -->
                    <div v-if="isSubtaskFormOpen" class="mt-5 mb-4 rounded-lg border bg-gray-50 p-3">
                        <h5 class="mb-3 text-sm font-medium">{{ editingSubtask ? 'Edit Subtask' : 'Create Subtask' }}</h5>

                        <div class="space-y-3">
                            <div>
                                <Label for="subtask-title" class="text-xs">Title *</Label>
                                <Input
                                    id="subtask-title"
                                    v-model="subtaskForm.title"
                                    placeholder="Enter subtask title"
                                    required
                                    class="h-8 text-xs"
                                />
                            </div>

                            <div>
                                <Label for="subtask-description" class="text-xs">Description</Label>
                                <Textarea
                                    id="subtask-description"
                                    v-model="subtaskForm.description"
                                    placeholder="Enter subtask description"
                                    rows="2"
                                    class="text-xs"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <Label class="text-xs">Status</Label>
                                    <Select v-model="subtaskForm.status">
                                        <SelectTrigger class="h-8 text-xs">
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

                                <div>
                                    <Label class="text-xs">Priority</Label>
                                    <Select v-model="subtaskForm.priority">
                                        <SelectTrigger class="h-8 text-xs">
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

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <Label class="text-xs">Start Date</Label>
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button variant="outline" class="h-8 w-full justify-start text-left text-xs font-normal">
                                                <Calendar class="mr-1 h-3 w-3" />
                                                {{
                                                    subtaskStartDateValue ? df.format(subtaskStartDateValue.toDate(getLocalTimeZone())) : 'Pick date'
                                                }}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-auto p-0">
                                            <CalendarComponent v-model="subtaskStartDateValue" />
                                        </PopoverContent>
                                    </Popover>
                                </div>

                                <div>
                                    <Label class="text-xs">Due Date</Label>
                                    <Popover>
                                        <PopoverTrigger as-child>
                                            <Button variant="outline" class="h-8 w-full justify-start text-left text-xs font-normal">
                                                <Calendar class="mr-1 h-3 w-3" />
                                                {{ subtaskDueDateValue ? df.format(subtaskDueDateValue.toDate(getLocalTimeZone())) : 'Pick date' }}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-auto p-0">
                                            <CalendarComponent v-model="subtaskDueDateValue" />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>

                            <!-- Tags Section -->
                            <div class="space-y-2">
                                <Label class="text-xs">Tags</Label>

                                <!-- Selected Tags Display -->
                                <div v-if="subtaskForm.tag_ids.length > 0" class="flex flex-wrap gap-1 rounded-md border bg-white p-2">
                                    <Badge
                                        v-for="tagId in subtaskForm.tag_ids"
                                        :key="tagId"
                                        variant="outline"
                                        class="cursor-pointer px-1.5 py-0.5 text-xs hover:bg-red-50"
                                        :style="`border-color: ${availableTags.find((t) => t.id === tagId)?.color}; color: ${availableTags.find((t) => t.id === tagId)?.color}`"
                                        @click="
                                            () => {
                                                const index = subtaskForm.tag_ids.indexOf(tagId);
                                                if (index > -1) {
                                                    subtaskForm.tag_ids.splice(index, 1);
                                                }
                                            }
                                        "
                                    >
                                        {{ availableTags.find((t) => t.id === tagId)?.name }}
                                        <span class="ml-1 text-xs">×</span>
                                    </Badge>
                                </div>

                                <!-- Available Tags Selection -->
                                <div v-if="availableTags.length > 0">
                                    <div class="flex max-h-20 flex-wrap gap-1 overflow-y-auto rounded-md border bg-white p-2">
                                        <Badge
                                            v-for="tag in availableTags.filter((t) => !subtaskForm.tag_ids.includes(t.id))"
                                            :key="tag.id"
                                            variant="outline"
                                            class="cursor-pointer px-1.5 py-0.5 text-xs transition-colors hover:bg-gray-50"
                                            @click="
                                                () => {
                                                    if (!subtaskForm.tag_ids.includes(tag.id)) {
                                                        subtaskForm.tag_ids.push(tag.id);
                                                    }
                                                }
                                            "
                                        >
                                            <span class="mr-1 text-xs">+</span>
                                            {{ tag.name }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Create New Tag -->
                                <div class="space-y-2 border-t pt-2">
                                    <Label class="text-xs font-medium text-gray-600">Create New Tag</Label>
                                    <div class="flex items-center space-x-2">
                                        <Input v-model="newTagForm.name" placeholder="Tag name" class="h-7 flex-1 text-xs" />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="createNewTag"
                                            :disabled="!newTagForm.name.trim() || isCreatingTag"
                                            class="h-7 px-2 text-xs"
                                        >
                                            {{ isCreatingTag ? 'Adding...' : 'Add' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 pt-2">
                                <Button @click="submitSubtaskForm" :disabled="isSubmitting" size="sm" class="h-7 px-3 text-xs">
                                    {{ isSubmitting ? 'Saving...' : editingSubtask ? 'Update' : 'Create' }}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        () => {
                                            isSubtaskFormOpen = false;
                                            editingSubtask = null;
                                            resetSubtaskForm();
                                        }
                                    "
                                    class="h-7 px-3 text-xs"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="getSubtasks(selectedTask.id).length === 0" class="py-6 text-center text-gray-500">
                        <p class="text-sm">No subtasks yet</p>
                        <Button variant="outline" size="sm" @click="openSubtaskForm(selectedTask)" class="mt-2 h-7 px-3 text-xs">
                            <Plus class="mr-1 h-3 w-3" />
                            Add First Subtask
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overlay -->
        <div v-if="isTaskDetailModalOpen" class="bg-opacity-20 fixed inset-0 z-40 bg-[#e6e6e68f]" @click="closeTaskDetailModal"></div>

        <!-- Add/Edit Task Dialog (Main Tasks) -->
        <Dialog
            :open="isAddTaskModalOpen || isEditTaskModalOpen"
            @update:open="
                (open) => {
                    if (!open) closeTaskModals();
                }
            "
        >
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[700px]">
                <DialogHeader>
                    <DialogTitle>{{ editingTask ? 'Edit Task' : 'Add New Task' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingTask ? 'Update the task details below.' : 'Fill in the details to create a new task.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitTaskForm" class="grid gap-4 py-4">
                    <!-- Task Title -->
                    <div class="grid grid-cols-4 items-center gap-4">
                        <Label for="task-title" class="text-right">Title *</Label>
                        <div class="col-span-3">
                            <Input id="task-title" v-model="taskForm.title" placeholder="Enter task title" required />
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="grid grid-cols-4 items-start gap-4">
                        <Label for="task-description" class="pt-2 text-right">Description</Label>
                        <div class="col-span-3">
                            <Textarea id="task-description" v-model="taskForm.description" placeholder="Enter task description" rows="3" />
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
                                        {{ startDateValue ? df.format(startDateValue.toDate(getLocalTimeZone())) : 'Pick date' }}
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
                                        {{ dueDateValue ? df.format(dueDateValue.toDate(getLocalTimeZone())) : 'Pick date' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <CalendarComponent v-model="dueDateValue" />
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeTaskModals" :disabled="isSubmitting"> Cancel </Button>
                    <Button type="button" @click="submitTaskForm" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : editingTask ? 'Update Task' : 'Create Task' }}
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
                        Are you sure you want to delete "{{ taskToDelete?.title }}"? This action cannot be undone and will permanently remove the task
                        and all its subtasks.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        @click="
                            () => {
                                isDeleteTaskDialogOpen = false;
                                taskToDelete = null;
                            }
                        "
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction @click="confirmDeleteTask" class="bg-red-600 hover:bg-red-700 focus:ring-red-600">
                        Delete Task
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
