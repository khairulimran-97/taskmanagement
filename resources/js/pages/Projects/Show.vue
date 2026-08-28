<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import DeleteTaskDialog from '@/components/project/DeleteTaskDialog.vue';
import ProjectHeader from '@/components/project/ProjectHeader.vue';
import ProjectStats from '@/components/project/ProjectStats.vue';
import SubtaskForm from '@/components/project/SubtaskForm.vue';
import TaskDetailSidebar from '@/components/project/TaskDetailSidebar.vue';
import TaskForm from '@/components/project/TaskForm.vue';
import TaskKanbanBoard from '@/components/project/TaskKanbanBoard.vue';
import TaskList from '@/components/project/TaskList.vue';
import EditProjectDialog from './Edit.vue';

import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Columns3, LayoutList, Search, X } from 'lucide-vue-next';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    tags: {
        type: Array,
        required: true,
    },
    completionPercentage: {
        type: Number,
        required: true,
    },
});

// Breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Projects', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
]);

// State
const isEditProjectOpen = ref(false);
const isAddTaskModalOpen = ref(false);
const isEditTaskModalOpen = ref(false);
const editingTask = ref(null);
const isDeleteTaskDialogOpen = ref(false);
const taskToDelete = ref(null);
const isSubmitting = ref(false);
const isDeletingTask = ref(false);
const updatingTasks = ref(new Set());

// Right side modal for task details
const isTaskDetailModalOpen = ref(false);
const selectedTask = ref(null);
const isSubtaskFormOpen = ref(false);
const editingSubtask = ref(null);

// Available tags from props (reactive)
const availableTags = ref([...props.tags]);

// View mode (table / kanban) - persisted to localStorage (storage can be unavailable)
const VIEW_PREF_KEY = 'project-view-mode';
const readViewPref = () => {
    try {
        return localStorage.getItem(VIEW_PREF_KEY) || 'table';
    } catch {
        return 'table';
    }
};
const viewMode = ref(readViewPref());

watch(viewMode, (val) => {
    try {
        localStorage.setItem(VIEW_PREF_KEY, val);
    } catch {
        // Storage unavailable — the view still switches for this session
    }
});

// Search and filter
const searchQuery = ref('');
const filterPriority = ref('all');

// Status and priority configuration — token-based, matches the shared status language
const priorityConfig = {
    urgent: {
        key: 'urgent',
        label: 'Urgent priority',
        class: 'bg-destructive/10 text-destructive',
        headerClass: 'bg-destructive/10 text-destructive',
        hoverClass: 'hover:bg-destructive/5',
    },
    high: {
        key: 'high',
        label: 'High priority',
        class: 'bg-warning/10 text-warning',
        headerClass: 'bg-warning/10 text-warning',
        hoverClass: 'hover:bg-warning/5',
    },
    medium: {
        key: 'medium',
        label: 'Medium priority',
        class: 'bg-warning/10 text-warning',
        headerClass: 'bg-warning/10 text-warning',
        hoverClass: 'hover:bg-warning/5',
    },
    low: {
        key: 'low',
        label: 'Low priority',
        class: 'bg-muted text-muted-foreground',
        headerClass: 'bg-muted text-muted-foreground',
        hoverClass: 'hover:bg-muted/50',
    },
};

const statusConfig = {
    todo: {
        label: 'To do',
        icon: 'Circle',
        class: 'bg-muted text-muted-foreground',
        iconClass: 'text-muted-foreground',
    },
    in_progress: {
        label: 'In progress',
        icon: 'PlayCircle',
        class: 'bg-primary/10 text-primary',
        iconClass: 'text-primary',
    },
    completed: {
        label: 'Completed',
        icon: 'CheckCircle2',
        class: 'bg-success/10 text-success',
        iconClass: 'text-success',
    },
    cancelled: {
        label: 'Cancelled',
        icon: 'XCircle',
        class: 'bg-destructive/10 text-destructive',
        iconClass: 'text-destructive',
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
            onError: () => {
                toast.error('Failed to update task status');
            },
            onFinish: () => {
                updatingTasks.value.delete(task.id);
            },
        },
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
            const firstError = Object.values(errors)[0];
            toast.error(typeof firstError === 'string' ? firstError : 'Failed to save task');
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
            const firstError = Object.values(errors)[0];
            toast.error(typeof firstError === 'string' ? firstError : 'Failed to save subtask');
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const createNewTag = (tagData) => {
    router.post(route('tags.store'), tagData, {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.flash && page.props.flash.newTag) {
                const newTag = page.props.flash.newTag;
                availableTags.value.push(newTag);
            }
        },
        onError: () => {
            toast.error('Failed to create tag');
        },
    });
};

// Task reordering method
const reorderTasks = (updates) => {
    router.post(
        route('tasks.reorder'),
        { updates },
        {
            preserveScroll: true,
            onError: () => {
                toast.error('Failed to reorder tasks');
            },
        },
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
    if (!taskToDelete.value || isDeletingTask.value) return;

    isDeletingTask.value = true;

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
        onError: () => {
            toast.error('Failed to delete task');
        },
        onFinish: () => {
            isDeletingTask.value = false;
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

        <PageContainer>
            <!-- Project Header -->
            <div class="mb-6 space-y-4">
                <ProjectHeader :project="project" @edit="isEditProjectOpen = true" @add-task="openAddTaskModal()" />
                <ProjectStats :project="project" :completion-percentage="completionPercentage" />
            </div>

            <!-- Toolbar -->
            <Tabs :default-value="viewMode" @update:model-value="(val) => (viewMode = val)">
                <div class="border-border mb-4 flex flex-col gap-2 border-b pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-1 flex-wrap items-center gap-2.5">
                        <TabsList class="bg-muted h-9 shrink-0 rounded-lg p-1">
                            <TabsTrigger
                                value="table"
                                class="data-[state=active]:bg-card flex h-7 items-center gap-1.5 rounded-md px-3 text-sm data-[state=active]:shadow-xs"
                            >
                                <LayoutList class="size-4" />
                                List
                            </TabsTrigger>
                            <TabsTrigger
                                value="kanban"
                                class="data-[state=active]:bg-card flex h-7 items-center gap-1.5 rounded-md px-3 text-sm data-[state=active]:shadow-xs"
                            >
                                <Columns3 class="size-4" />
                                Board
                            </TabsTrigger>
                        </TabsList>

                        <!-- Filters -->
                        <div class="flex w-full flex-1 items-center gap-2 sm:w-auto">
                            <div class="relative flex-1">
                                <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input v-model="searchQuery" placeholder="Filter tasks…" class="bg-card h-9 w-full pr-8 pl-8 text-sm" />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    aria-label="Clear search"
                                    @click="searchQuery = ''"
                                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 absolute top-1/2 right-1.5 flex size-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>

                            <Select v-model="filterPriority">
                                <SelectTrigger class="bg-card h-9 w-36 shrink-0 text-sm">
                                    <SelectValue placeholder="Priority" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All priorities</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="low">Low</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
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
        </PageContainer>

        <!-- Task Detail Slideover with Subtask Form using slot -->
        <TaskDetailSidebar
            :project="project"
            :is-open="isTaskDetailModalOpen"
            :selected-task="selectedTask"
            :status-config="statusConfig"
            :priority-config="priorityConfig"
            :updating-tasks="updatingTasks"
            @close="closeTaskDetailModal"
            @toggle-task="toggleTaskCompletion"
            @edit-task="handleEditTask"
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
                    @cancel="
                        isSubtaskFormOpen = false;
                        editingSubtask = null;
                    "
                    @create-tag="createNewTag"
                />
            </template>
        </TaskDetailSidebar>

        <!-- Task Form Modal -->
        <TaskForm
            :is-open="isAddTaskModalOpen || isEditTaskModalOpen"
            :project-id="project.id"
            :editing-task="editingTask"
            :is-submitting="isSubmitting"
            :available-tags="availableTags"
            @update:open="
                (open) => {
                    if (!open) closeTaskModals();
                }
            "
            @submit="submitTaskForm"
            @cancel="closeTaskModals"
            @create-tag="createNewTag"
        />

        <!-- Delete Task Confirmation Dialog -->
        <DeleteTaskDialog
            :is-open="isDeleteTaskDialogOpen"
            :task-to-delete="taskToDelete"
            :processing="isDeletingTask"
            @update:open="isDeleteTaskDialogOpen = $event"
            @confirm="confirmDeleteTask"
            @cancel="
                isDeleteTaskDialogOpen = false;
                taskToDelete = null;
            "
        />

        <!-- Edit Project Dialog -->
        <EditProjectDialog
            :open="isEditProjectOpen"
            :project="project"
            @update:open="isEditProjectOpen = $event"
            @success="isEditProjectOpen = false"
        />
    </AppLayout>
</template>
