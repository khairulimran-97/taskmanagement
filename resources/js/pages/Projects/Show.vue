<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';

import ProjectHeader from '@/components/project/ProjectHeader.vue';
import ProjectStats from '@/components/project/ProjectStats.vue';
import TaskList from '@/components/project/TaskList.vue';
import TaskKanbanBoard from '@/components/project/TaskKanbanBoard.vue';
import TaskDetailSidebar from '@/components/project/TaskDetailSidebar.vue';
import TaskForm from '@/components/project/TaskForm.vue';
import SubtaskForm from '@/components/project/SubtaskForm.vue';
import DeleteTaskDialog from '@/components/project/DeleteTaskDialog.vue';

import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Plus, Search, LayoutList, Columns3, X } from 'lucide-vue-next';

const props = defineProps({
    project: {
        type: Object,
        required: true
    },
    tags: {
        type: Array,
        required: true
    },
    completionPercentage: {
        type: Number,
        required: true
    }
});

// Breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
]);

// State
const isAddTaskModalOpen = ref(false);
const isEditTaskModalOpen = ref(false);
const editingTask = ref(null);
const isDeleteTaskDialogOpen = ref(false);
const taskToDelete = ref(null);
const isSubmitting = ref(false);
const updatingTasks = ref(new Set());

// Right side modal for task details
const isTaskDetailModalOpen = ref(false);
const selectedTask = ref(null);
const isSubtaskFormOpen = ref(false);
const editingSubtask = ref(null);

// Available tags from props (reactive)
const availableTags = ref([...props.tags]);

// View mode (table / kanban) - persisted to localStorage
const VIEW_PREF_KEY = 'project-view-mode';
const viewMode = ref(localStorage.getItem(VIEW_PREF_KEY) || 'table');

watch(viewMode, (val) => {
    localStorage.setItem(VIEW_PREF_KEY, val);
});

// Search and filter
const searchQuery = ref('');
const filterPriority = ref('all');

// Status and priority configuration
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

const statusConfig = {
    todo: {
        label: 'To Do',
        icon: 'Circle',
        class: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
        iconClass: 'text-slate-400 hover:text-slate-600',
    },
    in_progress: {
        label: 'In Progress',
        icon: 'PlayCircle',
        class: 'bg-blue-100 text-blue-800 hover:bg-blue-200',
        iconClass: 'text-blue-500 hover:text-blue-700',
    },
    completed: {
        label: 'Completed',
        icon: 'CheckCircle2',
        class: 'bg-green-100 text-green-800 hover:bg-green-200',
        iconClass: 'text-green-500 hover:text-green-700',
    },
    cancelled: {
        label: 'Cancelled',
        icon: 'XCircle',
        class: 'bg-red-100 text-red-800 hover:bg-red-200',
        iconClass: 'text-red-500 hover:text-red-700',
    },
};

// Methods
const openAddTaskModal = () => {
    isAddTaskModalOpen.value = true;
    isEditTaskModalOpen.value = false;
    editingTask.value = null;
};

const openEditTaskModal = (task) => {
    editingTask.value = task;
    isEditTaskModalOpen.value = true;
    isAddTaskModalOpen.value = false;
};

const openTaskDetailModal = (task) => {
    selectedTask.value = task;
    isTaskDetailModalOpen.value = true;
    isSubtaskFormOpen.value = false;
    editingSubtask.value = null;
};

const closeTaskDetailModal = () => {
    isTaskDetailModalOpen.value = false;
    selectedTask.value = null;
    isSubtaskFormOpen.value = false;
    editingSubtask.value = null;
};

const openSubtaskForm = (parentTask) => {
    if (!parentTask) return;
    isSubtaskFormOpen.value = true;
    editingSubtask.value = null;
};

const editSubtask = (subtask) => {
    editingSubtask.value = subtask;
    isSubtaskFormOpen.value = true;
};

const toggleTaskCompletion = (task) => {
    if (updatingTasks.value.has(task.id)) return;

    updatingTasks.value.add(task.id);
    const newStatus = task.status === 'completed' ? 'todo' : 'completed';

    router.put(
        route('tasks.update', task.id),
        { status: newStatus },
        {
            preserveScroll: true,
            onError: (errors) => {
                console.error('Failed to update task status:', errors);
            },
            onFinish: () => {
                updatingTasks.value.delete(task.id);
            },
        }
    );
};

const submitTaskForm = (formData) => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    const url = editingTask.value ? route('tasks.update', editingTask.value.id) : route('tasks.store');
    const method = editingTask.value ? 'put' : 'post';

    router[method](url, formData, {
        preserveScroll: true,
        onSuccess: () => {
            isAddTaskModalOpen.value = false;
            isEditTaskModalOpen.value = false;
            editingTask.value = null;
        },
        onError: (errors) => {
            console.error('Task submission errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const submitSubtaskForm = (formData) => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    const url = editingSubtask.value ? route('tasks.update', editingSubtask.value.id) : route('tasks.store');
    const method = editingSubtask.value ? 'put' : 'post';

    router[method](url, formData, {
        preserveScroll: true,
        onSuccess: () => {
            isSubtaskFormOpen.value = false;
            editingSubtask.value = null;
        },
        onError: (errors) => {
            console.error('Subtask submission errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const createNewTag = (tagData) => {
    router.post(
        route('tags.store'),
        tagData,
        {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash && page.props.flash.newTag) {
                    const newTag = page.props.flash.newTag;
                    availableTags.value.push(newTag);
                }
            },
            onError: (errors) => {
                console.error('Failed to create tag:', errors);
            }
        }
    );
};

// Task reordering method
const reorderTasks = (updates) => {
    router.post(
        route('tasks.reorder'),
        { updates },
        {
            preserveScroll: true,
            onError: (errors) => {
                console.error('Failed to reorder tasks:', errors);
                // Inertia will automatically handle showing the error message
            }
        }
    );
};

watch(
    () => props.tags,
    (newTags) => {
        availableTags.value = [...newTags];
    },
    { deep: true },
);

const deleteTask = (task) => {
    taskToDelete.value = task;
    isDeleteTaskDialogOpen.value = true;
};

const handleViewTags = (task) => {
    selectedTask.value = task;
    isTaskDetailModalOpen.value = true;
};

const confirmDeleteTask = () => {
    if (!taskToDelete.value) return;

    router.delete(route('tasks.destroy', taskToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteTaskDialogOpen.value = false;

            // Close task detail modal if deleted task was selected
            if (selectedTask.value && selectedTask.value.id === taskToDelete.value.id) {
                closeTaskDetailModal();
            }

            taskToDelete.value = null;
        },
    });
};

const closeTaskModals = () => {
    isAddTaskModalOpen.value = false;
    isEditTaskModalOpen.value = false;
    editingTask.value = null;
};

const handleEditTask = (task) => {
    openEditTaskModal(task);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />

        <div class="container mx-auto px-4 py-6">
            <!-- Project Header -->
            <div class="mb-6">
                <ProjectHeader :project="project" />

                <!-- Project Stats -->
                <ProjectStats
                    :project="project"
                    :completion-percentage="completionPercentage"
                />
            </div>

            <!-- View Switcher + Search/Filter Toolbar -->
            <Tabs :default-value="viewMode" @update:model-value="(val) => viewMode = val">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <TabsList class="grid w-auto grid-cols-2">
                        <TabsTrigger value="table" class="flex items-center gap-1.5 px-3 text-xs">
                            <LayoutList class="h-3.5 w-3.5" />
                            Table
                        </TabsTrigger>
                        <TabsTrigger value="kanban" class="flex items-center gap-1.5 px-3 text-xs">
                            <Columns3 class="h-3.5 w-3.5" />
                            Board
                        </TabsTrigger>
                    </TabsList>

                    <div class="flex items-center gap-2">
                        <!-- Search -->
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search tasks..."
                                class="h-8 w-48 pl-8 text-xs"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>

                        <!-- Priority Filter -->
                        <Select v-model="filterPriority">
                            <SelectTrigger class="h-8 w-32 text-xs">
                                <SelectValue placeholder="Priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Priority</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Add Task Button -->
                        <Button @click="openAddTaskModal()" class="flex h-8 items-center gap-1.5 px-3 text-xs shadow-sm">
                            <Plus class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Add Task</span>
                        </Button>
                    </div>
                </div>

                <!-- Table View -->
                <TabsContent value="table" class="mt-0">
                    <TaskList
                        :project="project"
                        :updating-tasks="updatingTasks"
                        :search-query="searchQuery"
                        :filter-priority="filterPriority"
                        @add-task="openAddTaskModal"
                        @view-task="openTaskDetailModal"
                        @toggle-task="toggleTaskCompletion"
                        @delete-task="deleteTask"
                        @view-tags="handleViewTags"
                        @edit-task="handleEditTask"
                        @reorder-tasks="reorderTasks"
                    />
                </TabsContent>

                <!-- Kanban View -->
                <TabsContent value="kanban" class="mt-0">
                    <TaskKanbanBoard
                        :project="project"
                        :updating-tasks="updatingTasks"
                        :search-query="searchQuery"
                        :filter-priority="filterPriority"
                        @add-task="openAddTaskModal"
                        @view-task="openTaskDetailModal"
                        @toggle-task="toggleTaskCompletion"
                        @delete-task="deleteTask"
                        @view-tags="handleViewTags"
                        @edit-task="handleEditTask"
                    />
                </TabsContent>
            </Tabs>
        </div>

        <!-- Task Detail Sidebar with Subtask Form using slot -->
        <div class="relative">
            <TaskDetailSidebar
                :project="project"
                :is-open="isTaskDetailModalOpen"
                :selected-task="selectedTask"
                :status-config="statusConfig"
                :priority-config="priorityConfig"
                :updating-tasks="updatingTasks"
                @close="closeTaskDetailModal"
                @toggle-task="toggleTaskCompletion"
                @edit-subtask="editSubtask"
                @delete-task="deleteTask"
                @add-subtask="openSubtaskForm"
                @reorder-tasks="reorderTasks"
            >
                <!-- Subtask Form slot -->
                <template #subtask-form>
                    <SubtaskForm
                        v-if="selectedTask && isSubtaskFormOpen"
                        :is-open="isSubtaskFormOpen"
                        :project-id="project.id"
                        :parent-task-id="selectedTask.id"
                        :editing-subtask="editingSubtask"
                        :available-tags="availableTags"
                        :is-submitting="isSubmitting"
                        @submit="submitSubtaskForm"
                        @cancel="isSubtaskFormOpen = false; editingSubtask = null"
                        @create-tag="createNewTag"
                    />
                </template>
            </TaskDetailSidebar>
        </div>

        <!-- Overlay -->
        <div v-if="isTaskDetailModalOpen" class="bg-opacity-20 fixed inset-0 z-40 bg-[#e6e6e68f]" @click="closeTaskDetailModal"></div>

        <!-- Task Form Modal -->
        <TaskForm
            :is-open="isAddTaskModalOpen || isEditTaskModalOpen"
            :project-id="project.id"
            :editing-task="editingTask"
            :is-submitting="isSubmitting"
            :available-tags="availableTags"
            @update:open="(open) => { if (!open) closeTaskModals(); }"
            @submit="submitTaskForm"
            @cancel="closeTaskModals"
            @create-tag="createNewTag"
        />

        <!-- Delete Task Confirmation Dialog -->
        <DeleteTaskDialog
            :is-open="isDeleteTaskDialogOpen"
            :task-to-delete="taskToDelete"
            @update:open="isDeleteTaskDialogOpen = $event"
            @confirm="confirmDeleteTask"
            @cancel="isDeleteTaskDialogOpen = false; taskToDelete = null"
        />
    </AppLayout>
</template>
