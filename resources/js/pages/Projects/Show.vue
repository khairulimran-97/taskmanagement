<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageContainer from '@/components/PageContainer.vue';
import { BreadcrumbItem } from '@/types';

import ProjectHeader from '@/components/project/ProjectHeader.vue';
import ProjectStats from '@/components/project/ProjectStats.vue';
import TaskList from '@/components/project/TaskList.vue';
import TaskKanbanBoard from '@/components/project/TaskKanbanBoard.vue';
import TaskDetailSidebar from '@/components/project/TaskDetailSidebar.vue';
import TaskForm from '@/components/project/TaskForm.vue';
import SubtaskForm from '@/components/project/SubtaskForm.vue';
import DeleteTaskDialog from '@/components/project/DeleteTaskDialog.vue';
import EditProjectDialog from './Edit.vue';

import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Search, LayoutList, Columns3, X } from 'lucide-vue-next';

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
const isEditProjectOpen = ref(false);
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
        class: 'bg-destructive/12 text-destructive border-destructive/25',
        headerClass: 'bg-destructive/8 border-destructive/20 text-destructive',
        hoverClass: 'hover:bg-destructive/5',
    },
    high: {
        key: 'high',
        label: 'High Priority',
        emoji: '⚠️',
        class: 'bg-orange-500/15 text-orange-700 dark:text-orange-400 border-orange-500/25',
        headerClass: 'bg-orange-500/8 border-orange-500/20 text-orange-700 dark:text-orange-400',
        hoverClass: 'hover:bg-orange-500/5',
    },
    medium: {
        key: 'medium',
        label: 'Medium Priority',
        emoji: '📋',
        class: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/25',
        headerClass: 'bg-amber-500/8 border-amber-500/20 text-amber-700 dark:text-amber-400',
        hoverClass: 'hover:bg-amber-500/5',
    },
    low: {
        key: 'low',
        label: 'Low Priority',
        emoji: '📝',
        class: 'bg-[hsl(150_24%_42%/0.14)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)] border-[hsl(150_24%_42%/0.25)]',
        headerClass: 'bg-[hsl(150_24%_42%/0.08)] border-[hsl(150_24%_42%/0.2)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)]',
        hoverClass: 'hover:bg-[hsl(150_24%_42%/0.05)]',
    },
};

const statusConfig = {
    todo: {
        label: 'To Do',
        icon: 'Circle',
        class: 'bg-muted text-muted-foreground hover:bg-muted/70',
        iconClass: 'text-muted-foreground hover:text-foreground',
    },
    in_progress: {
        label: 'In Progress',
        icon: 'PlayCircle',
        class: 'bg-primary/12 text-primary hover:bg-primary/20',
        iconClass: 'text-primary hover:text-primary',
    },
    completed: {
        label: 'Completed',
        icon: 'CheckCircle2',
        class: 'bg-[hsl(150_24%_42%/0.14)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)] hover:bg-[hsl(150_24%_42%/0.2)]',
        iconClass: 'text-[hsl(150_24%_45%)] hover:text-[hsl(150_30%_38%)]',
    },
    cancelled: {
        label: 'Cancelled',
        icon: 'XCircle',
        class: 'bg-destructive/12 text-destructive hover:bg-destructive/20',
        iconClass: 'text-destructive hover:text-destructive',
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

        <PageContainer>
            <!-- Project Header -->
            <div class="mb-6 space-y-4">
                <ProjectHeader :project="project" @edit="isEditProjectOpen = true" @add-task="openAddTaskModal()" />
                <ProjectStats :project="project" :completion-percentage="completionPercentage" />
            </div>

            <!-- Toolbar -->
            <Tabs :default-value="viewMode" @update:model-value="(val) => viewMode = val">
                <div class="mb-4 flex flex-col gap-2 border-b border-border pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-1 items-center gap-2.5">
                        <TabsList class="h-9 shrink-0 rounded-lg bg-muted p-1">
                            <TabsTrigger value="table" class="flex h-7 items-center gap-1.5 rounded-md px-3 text-sm data-[state=active]:bg-card data-[state=active]:shadow-sm">
                                <LayoutList class="h-4 w-4" />
                                List
                            </TabsTrigger>
                            <TabsTrigger value="kanban" class="flex h-7 items-center gap-1.5 rounded-md px-3 text-sm data-[state=active]:bg-card data-[state=active]:shadow-sm">
                                <Columns3 class="h-4 w-4" />
                                Board
                            </TabsTrigger>
                        </TabsList>

                        <!-- Filters -->
                        <div class="flex flex-1 items-center gap-2">
                            <div class="relative flex-1">
                                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Filter tasks…"
                                    class="h-9 w-full rounded-lg border-border bg-background pl-8 pr-8 text-sm shadow-none focus-visible:ring-1"
                                />
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <Select v-model="filterPriority">
                                <SelectTrigger class="h-9 w-32 shrink-0 rounded-lg border-border bg-background text-sm shadow-none">
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
                        @cancel="isSubtaskFormOpen = false; editingSubtask = null"
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

        <!-- Edit Project Dialog -->
        <EditProjectDialog
            :open="isEditProjectOpen"
            :project="project"
            @update:open="isEditProjectOpen = $event"
            @success="isEditProjectOpen = false"
        />
    </AppLayout>
</template>
