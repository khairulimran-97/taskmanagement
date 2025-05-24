<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BreadcrumbItem, Project } from '@/types';
import { CheckCircle2, Edit, GripVertical, Loader2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';

import CreateProjectDialog from './Create.vue';
import EditProjectDialog from './Edit.vue';

interface Props {
    projects: Project[];
}

const props = defineProps<Props>();

// Define breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    {
        title: 'Dashboard',
        href: route('dashboard')
    },
    {
        title: 'Projects',
        href: route('projects.index')
    }
]);

// Modal states
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const editingProject = ref<Project | null>(null);
const isDeleteDialogOpen = ref(false);
const projectToDelete = ref<Project | null>(null);

// Drag and drop states
const draggedProject = ref<Project | null>(null);
const isDragging = ref(false);
const isReordering = ref(false);
const dragOverIndex = ref<number | null>(null);
const optimisticProjects = ref<Project[]>([...props.projects]);

// Watch for props changes to update optimistic state
watch(() => props.projects, (newProjects) => {
    if (!isReordering.value) {
        optimisticProjects.value = [...newProjects];
    }
}, { deep: true });

// Computed sorted projects
const sortedProjects = computed(() => {
    return [...optimisticProjects.value].sort((a, b) => {
        const aOrder = a.sort_order ?? 999999;
        const bOrder = b.sort_order ?? 999999;
        return aOrder - bOrder;
    });
});

// Open edit modal with project data
const openEditModal = (project: Project) => {
    editingProject.value = project;
    isEditModalOpen.value = true;
};

// Handle successful creation
const handleCreateSuccess = (project: any) => {
};

// Handle successful edit
const handleEditSuccess = (project: any) => {
    editingProject.value = null;
};

// Handle edit modal close
const handleEditModalClose = (isOpen: boolean) => {
    isEditModalOpen.value = isOpen;
    if (!isOpen) {
        editingProject.value = null;
    }
};

// Handle project deletion
const openDeleteDialog = (project: Project) => {
    projectToDelete.value = project;
    isDeleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (projectToDelete.value) {
        router.delete(route('projects.destroy', projectToDelete.value.id), {
            preserveScroll: true,
            onError: () => {
            },
            onFinish: () => {
                isDeleteDialogOpen.value = false;
                projectToDelete.value = null;
            }
        });
    }
};

const cancelDelete = () => {
    isDeleteDialogOpen.value = false;
    projectToDelete.value = null;
};

// Drag and drop functions with improved UX
const handleDragStart = (event: DragEvent, project: Project) => {
    draggedProject.value = project;
    isDragging.value = true;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/html', '');
        // Add custom drag image
        const dragImage = document.createElement('div');
        dragImage.className = 'bg-blue-100 border-2 border-blue-300 rounded-lg p-3 shadow-lg';
        dragImage.innerHTML = `<div class="font-semibold text-blue-800">📋 ${project.name}</div>`;
        dragImage.style.position = 'absolute';
        dragImage.style.top = '-1000px';
        document.body.appendChild(dragImage);
        event.dataTransfer.setDragImage(dragImage, 0, 0);
        setTimeout(() => document.body.removeChild(dragImage), 0);
    }
};

const handleDragEnd = () => {
    draggedProject.value = null;
    isDragging.value = false;
    dragOverIndex.value = null;
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
};

const handleDragEnter = (event: DragEvent, index: number) => {
    event.preventDefault();
    if (draggedProject.value && draggedProject.value.id !== sortedProjects.value[index].id) {
        dragOverIndex.value = index;
    }
};

const handleDragLeave = (event: DragEvent, index: number) => {
    event.preventDefault();
    // Only clear if we're really leaving this specific row
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = event.clientX;
    const y = event.clientY;

    // Add some tolerance to prevent flickering
    const tolerance = 5;
    if (x < rect.left - tolerance || x > rect.right + tolerance ||
        y < rect.top - tolerance || y > rect.bottom + tolerance) {
        if (dragOverIndex.value === index) {
            dragOverIndex.value = null;
        }
    }
};

const handleDrop = (event: DragEvent, targetProject: Project, targetIndex: number) => {
    event.preventDefault();
    dragOverIndex.value = null;

    if (!draggedProject.value || draggedProject.value.id === targetProject.id) {
        return;
    }

    const sourceProject = draggedProject.value;
    const sourceIndex = sortedProjects.value.findIndex(p => p.id === sourceProject.id);

    if (sourceIndex === -1) return;

    // Optimistic update - immediately update the UI
    updateProjectOrderOptimistic(sourceProject, targetProject, sourceIndex, targetIndex);

    // Then send to server
    updateProjectOrderServer(sourceProject, targetProject, sourceIndex, targetIndex);
};

const updateProjectOrderOptimistic = (sourceProject: Project, targetProject: Project, sourceIndex: number, targetIndex: number) => {
    // Create a copy of the sorted projects array
    const newProjects = [...sortedProjects.value];

    // Remove the dragged project from its current position
    const [movedProject] = newProjects.splice(sourceIndex, 1);

    // Calculate the correct insertion index after removal
    let insertIndex = targetIndex;
    if (sourceIndex < targetIndex) {
        // When moving down, the target index shifts left by 1 after removal
        insertIndex = targetIndex;
    } else {
        // When moving up, the target index stays the same
        insertIndex = targetIndex;
    }

    // Insert the project at the new position
    newProjects.splice(insertIndex, 0, movedProject);

    // Update sort orders based on new positions
    newProjects.forEach((project, index) => {
        project.sort_order = index;
    });

    // Update the optimistic state
    optimisticProjects.value = newProjects;
};

const updateProjectOrderServer = (sourceProject: Project, targetProject: Project, sourceIndex: number, targetIndex: number) => {
    isReordering.value = true;

    // Get the current optimistic state for calculation
    const currentProjects = optimisticProjects.value;

    // Create updates array with new sort orders
    const updates: { id: number; sort_order: number }[] = [];

    // Simply assign sort_order based on the new positions
    currentProjects.forEach((project, index) => {
        updates.push({ id: project.id, sort_order: index });
    });

    // Send update to server
    router.post(route('projects.reorder'), { updates }, {
        preserveScroll: true,
        onError: () => {
            // Revert optimistic update on error
            optimisticProjects.value = [...props.projects];
        },
        onFinish: () => {
            isReordering.value = false;
        }
    });
};

// Format date helper
function formatDate(date: string | null): string {
    if (!date) return '-';

    const dateObj = new Date(date);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    }).format(dateObj);
}

// Get priority class for badge
function getPriorityClass(priority: string): string {
    switch (priority) {
        case 'high':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'low':
            return 'bg-green-100 text-green-800 border-green-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// Get status class for badge
function getStatusClass(status: string): string {
    switch (status) {
        case 'active':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'paused':
            return 'bg-orange-100 text-orange-800 border-orange-200';
        case 'completed':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'archived':
            return 'bg-gray-100 text-gray-800 border-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Projects" />
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Projects</h1>

                <CreateProjectDialog
                    v-model:open="isCreateModalOpen"
                    @success="handleCreateSuccess"
                />
            </div>

            <EditProjectDialog
                :open="isEditModalOpen"
                :project="editingProject"
                @update:open="handleEditModalClose"
                @success="handleEditSuccess"
            />

            <div class="mt-6 mb-10 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Total Projects</div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white">{{ props.projects.length }}</div>
                        </div>
                        <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <GripVertical class="w-6 h-6 text-gray-600 dark:text-gray-300" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Active</div>
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                {{ props.projects.filter(p => p.status === 'active').length }}
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                            <div class="w-3 h-3 bg-blue-600 dark:bg-blue-400 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Completed</div>
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                {{ props.projects.filter(p => p.status === 'completed').length }}
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                            <CheckCircle2 class="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">High Priority</div>
                            <div class="text-3xl font-bold text-red-600 dark:text-red-400">
                                {{ props.projects.filter(p => p.priority === 'high').length }}
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-lg flex items-center justify-center">
                            <div class="w-3 h-3 bg-red-600 dark:bg-red-400 rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 border-b border-blue-100 dark:border-gray-600 flex items-center justify-between">
                    <div class="flex items-center text-sm text-blue-700 dark:text-blue-300">
                        <GripVertical class="w-4 h-4 mr-2" />
                        <span class="font-medium">Drag & Drop to Reorder Projects</span>
                    </div>
                    <div v-if="isReordering" class="flex items-center text-sm text-blue-600 dark:text-blue-300">
                        <Loader2 class="w-4 h-4 mr-2 animate-spin" />
                        <span>Saving order...</span>
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow class="bg-gray-50 dark:bg-gray-800">
                            <TableHead class="w-8"></TableHead>
                            <TableHead class="w-8"></TableHead>
                            <TableHead class="font-semibold text-gray-700 dark:text-gray-200">Project Name</TableHead>
                            <TableHead class="font-semibold text-gray-700 dark:text-gray-200">Description</TableHead>
                            <TableHead class="font-semibold text-gray-700 dark:text-gray-200">Status</TableHead>
                            <TableHead class="font-semibold text-gray-700 dark:text-gray-200">Priority</TableHead>
                            <TableHead class="font-semibold text-gray-700 dark:text-gray-200">Progress</TableHead>
                            <TableHead class="text-right font-semibold text-gray-700 dark:text-gray-200">Actions</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-if="sortedProjects.length === 0">
                            <TableCell :colspan="10" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
                                        <GripVertical class="w-8 h-8 text-gray-300 dark:text-gray-500" />
                                    </div>
                                    <span class="text-lg font-medium">No projects yet</span>
                                    <span class="text-sm">Create your first project to get started</span>
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow
                            v-for="(project, index) in sortedProjects"
                            :key="project.id"
                            :class="[
                    'group transition-all duration-200 relative',
                    isDragging && draggedProject?.id === project.id
                        ? 'opacity-50 scale-95 bg-blue-50 dark:bg-blue-900 shadow-lg border-2 border-blue-200 dark:border-blue-700'
                        : 'hover:bg-gray-50 dark:hover:bg-gray-800',
                    dragOverIndex === index && draggedProject?.id !== project.id
                        ? 'bg-blue-50 dark:bg-blue-900 border-l-4 border-blue-500 dark:border-blue-400 shadow-sm transform scale-[1.01]'
                        : '',
                    isReordering ? 'pointer-events-none opacity-75' : 'cursor-move'
                ]"
                            draggable="true"
                            @dragstart="handleDragStart($event, project)"
                            @dragend="handleDragEnd"
                            @dragover="handleDragOver"
                            @dragenter="handleDragEnter($event, index)"
                            @dragleave="handleDragLeave($event, index)"
                            @drop="handleDrop($event, project, index)"
                        >
                            <TableCell class="p-3 relative">
                                <div
                                    class="flex items-center justify-center w-6 h-8 rounded transition-colors"
                                    :class="isDragging && draggedProject?.id === project.id
                            ? 'text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-800'
                            : 'text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 group-hover:bg-gray-100 dark:group-hover:bg-gray-800'"
                                >
                                    <GripVertical class="w-4 h-4" />
                                </div>
                                <div
                                    v-if="dragOverIndex === index && draggedProject?.id !== project.id"
                                    class="absolute top-0 left-2 right-2 h-0.5 bg-blue-500 rounded-full shadow-sm"
                                ></div>
                                <div
                                    v-if="dragOverIndex === index && draggedProject?.id !== project.id"
                                    class="absolute top-0 left-2 w-2 h-2 bg-blue-500 rounded-full transform -translate-y-1 shadow-sm"
                                ></div>
                            </TableCell>

                            <TableCell class="p-3">
                                <div
                                    class="w-4 h-4 rounded-full border-2 border-white dark:border-gray-900 shadow-sm"
                                    :style="`background-color: ${project.color || '#3B82F6'}`"
                                ></div>
                            </TableCell>

                            <!-- Project Name -->
                            <TableCell class="font-medium">
                                <Link
                                    :href="route('projects.show', project.id)"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline font-semibold transition-colors"
                                    @click.stop
                                >
                                    {{ project.name }}
                                </Link>
                            </TableCell>

                            <!-- Description -->
                            <TableCell class="max-w-xs">
                    <span class="text-gray-600 dark:text-gray-400 line-clamp-2">
                        {{ project.description || 'No description' }}
                    </span>
                            </TableCell>

                            <!-- Status -->
                            <TableCell>
                                <Badge :class="getStatusClass(project.status)" class="font-medium">
                                    <CheckCircle2 v-if="project.status === 'completed'" class="w-3 h-3 mr-1" />
                                    {{ project.status.charAt(0).toUpperCase() + project.status.slice(1) }}
                                </Badge>
                            </TableCell>

                            <!-- Priority -->
                            <TableCell>
                                <Badge :class="getPriorityClass(project.priority)" class="font-medium">
                                    {{ project.priority.charAt(0).toUpperCase() + project.priority.slice(1) }}
                                </Badge>
                            </TableCell>

                            <!-- Progress -->
                            <TableCell>
                                <div v-if="project.completion_percentage !== undefined" class="min-w-24">
                                    <div class="flex items-center space-x-2">
                                        <Progress
                                            :model-value="project.completion_percentage"
                                            class="flex-1"
                                            :class="{
                                    'bg-red-200 dark:bg-red-800 [&>div]:bg-red-500 dark:[&>div]:bg-red-400': project.completion_percentage < 25,
                                    'bg-orange-200 dark:bg-orange-800 [&>div]:bg-orange-500 dark:[&>div]:bg-orange-400': project.completion_percentage >= 25 && project.completion_percentage < 50,
                                    'bg-yellow-200 dark:bg-yellow-800 [&>div]:bg-yellow-500 dark:[&>div]:bg-yellow-400': project.completion_percentage >= 50 && project.completion_percentage < 75,
                                    'bg-green-200 dark:bg-green-800 [&>div]:bg-green-500 dark:[&>div]:bg-green-400': project.completion_percentage >= 75
                                }" />
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium min-w-8 text-right">
                                {{ project.completion_percentage }}%
                            </span>
                                    </div>
                                </div>
                                <span v-else class="text-gray-400 dark:text-gray-500 text-sm">-</span>
                            </TableCell>

                            <!-- Actions -->
                            <TableCell class="text-right">
                                <div class="flex justify-end space-x-2">
                                    <Button variant="ghost" size="sm" @click.stop="openEditModal(project)" class="transition-colors text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                        <Edit class="w-4 h-4 mr-1" />
                                        Edit
                                    </Button>
                                    <Button asChild variant="outline" size="sm" class="transition-colors">
                                        <Link :href="route('projects.show', project.id)" @click.stop class="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400">
                                            View
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click.stop="openDeleteDialog(project)"
                                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900 transition-colors"
                                    >
                                        <Trash2 class="w-4 h-4 mr-1" />
                                        Delete
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Delete Confirmation Dialog -->
            <AlertDialog :open="isDeleteDialogOpen" @update:open="isDeleteDialogOpen = $event">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Project</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{{ projectToDelete?.name }}"? This action cannot be undone and will permanently remove the project and all its associated data.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel @click="cancelDelete">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            @click="confirmDelete"
                            class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                        >
                            Delete Project
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
