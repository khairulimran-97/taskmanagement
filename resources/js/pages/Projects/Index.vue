<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
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
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { labelize } from '@/lib/projectMeta';
import { BreadcrumbItem, Project } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Activity, CheckCircle2, Edit, Eye, Flag, FolderKanban, GripVertical, Loader2, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

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
        href: route('dashboard'),
    },
    {
        title: 'Projects',
        href: route('projects.index'),
    },
]);

// Status language — keep identical to the task views (tokens only)
const statusBadgeClass = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-primary/10 text-primary',
        completed: 'bg-success/10 text-success',
        paused: 'bg-muted text-muted-foreground',
        archived: 'bg-muted text-muted-foreground',
    };
    return map[status] || 'bg-muted text-muted-foreground';
};

const priorityMeta = (priority: string) => {
    const map: Record<string, { text: string; dot: string }> = {
        urgent: { text: 'text-destructive', dot: 'bg-destructive' },
        high: { text: 'text-warning', dot: 'bg-warning' },
        medium: { text: 'text-warning', dot: 'bg-warning' },
        low: { text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' },
    };
    return map[priority] || { text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' };
};

// Modal states
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const editingProject = ref<Project | null>(null);
const isDeleteDialogOpen = ref(false);
const projectToDelete = ref<Project | null>(null);
const isDeleting = ref(false);

// Drag and drop states
const draggedProject = ref<Project | null>(null);
const isDragging = ref(false);
const isReordering = ref(false);
const dragOverIndex = ref<number | null>(null);
const optimisticProjects = ref<Project[]>([...props.projects]);

// Watch for props changes to update optimistic state
watch(
    () => props.projects,
    (newProjects) => {
        if (!isReordering.value) {
            optimisticProjects.value = [...newProjects];
        }
    },
    { deep: true },
);

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
const handleCreateSuccess = () => {};

// Handle successful edit
const handleEditSuccess = () => {
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
    if (projectToDelete.value && !isDeleting.value) {
        isDeleting.value = true;
        router.delete(route('projects.destroy', projectToDelete.value.id), {
            preserveScroll: true,
            onError: () => {
                toast.error('Failed to delete project');
            },
            onFinish: () => {
                isDeleting.value = false;
                isDeleteDialogOpen.value = false;
                projectToDelete.value = null;
            },
        });
    }
};

const cancelDelete = () => {
    if (isDeleting.value) return;
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
    if (x < rect.left - tolerance || x > rect.right + tolerance || y < rect.top - tolerance || y > rect.bottom + tolerance) {
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
    const sourceIndex = sortedProjects.value.findIndex((p) => p.id === sourceProject.id);

    if (sourceIndex === -1) return;

    // Optimistic update - immediately update the UI
    updateProjectOrderOptimistic(sourceProject, targetProject, sourceIndex, targetIndex);

    // Then send to server
    updateProjectOrderServer();
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

const updateProjectOrderServer = () => {
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
    router.post(
        route('projects.reorder'),
        { updates },
        {
            preserveScroll: true,
            onError: () => {
                // Revert optimistic update on error
                optimisticProjects.value = [...props.projects];
                toast.error('Failed to save project order');
            },
            onFinish: () => {
                isReordering.value = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Projects" />
        <PageContainer>
            <PageHeader title="Projects" description="Organize your work into projects and track progress.">
                <template #actions>
                    <CreateProjectDialog v-model:open="isCreateModalOpen" @success="handleCreateSuccess" />
                </template>
            </PageHeader>

            <EditProjectDialog :open="isEditModalOpen" :project="editingProject" @update:open="handleEditModalClose" @success="handleEditSuccess" />

            <div class="mt-6 mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard :icon="FolderKanban" label="Total projects" :value="props.projects.length" accent />
                <StatCard :icon="Activity" label="Active" :value="props.projects.filter((p) => p.status === 'active').length" hint="in progress" />
                <StatCard :icon="CheckCircle2" label="Completed" :value="props.projects.filter((p) => p.status === 'completed').length" hint="done" />
                <StatCard :icon="Flag" label="High priority" :value="props.projects.filter((p) => p.priority === 'high').length" hint="needs focus" />
            </div>

            <EmptyState
                v-if="sortedProjects.length === 0"
                :icon="FolderKanban"
                title="No projects yet"
                description="Create your first project to start organizing tasks."
            >
                <template #action>
                    <Button @click="isCreateModalOpen = true">
                        <Plus class="size-4" />
                        New project
                    </Button>
                </template>
            </EmptyState>

            <div v-else class="border-border bg-card overflow-x-auto rounded-lg border shadow-xs">
                <div v-if="isReordering" class="border-border text-muted-foreground flex items-center gap-2 border-b px-4 py-2 text-xs">
                    <Loader2 class="size-3.5 animate-spin" />
                    Saving order…
                </div>

                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/50">
                            <TableHead class="hidden w-8 sm:table-cell"></TableHead>
                            <TableHead class="w-8"></TableHead>
                            <TableHead class="text-muted-foreground text-xs font-medium">Name</TableHead>
                            <TableHead class="text-muted-foreground hidden text-xs font-medium lg:table-cell">Description</TableHead>
                            <TableHead class="text-muted-foreground hidden text-xs font-medium md:table-cell">Status</TableHead>
                            <TableHead class="text-muted-foreground hidden text-xs font-medium sm:table-cell">Priority</TableHead>
                            <TableHead class="text-muted-foreground hidden text-xs font-medium md:table-cell">Progress</TableHead>
                            <TableHead class="text-muted-foreground text-right text-xs font-medium">Actions</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow
                            v-for="(project, index) in sortedProjects"
                            :key="project.id"
                            :class="[
                                'group transition-colors duration-150',
                                isDragging && draggedProject?.id === project.id ? 'bg-muted/50 opacity-50' : 'hover:bg-muted/60',
                                dragOverIndex === index && draggedProject?.id !== project.id ? 'border-t-primary bg-primary/5 border-t-2' : '',
                                isReordering ? 'pointer-events-none opacity-60' : '',
                            ]"
                            @dragover="handleDragOver"
                            @dragenter="handleDragEnter($event, index)"
                            @dragleave="handleDragLeave($event, index)"
                            @drop="handleDrop($event, project, index)"
                        >
                            <TableCell class="hidden p-3 sm:table-cell">
                                <div
                                    draggable="true"
                                    role="button"
                                    aria-label="Drag to reorder"
                                    class="flex h-8 w-6 cursor-grab items-center justify-center rounded transition-colors duration-150 active:cursor-grabbing"
                                    :class="
                                        isDragging && draggedProject?.id === project.id
                                            ? 'bg-primary/10 text-primary'
                                            : 'text-muted-foreground/60 group-hover:bg-muted group-hover:text-foreground'
                                    "
                                    @dragstart="handleDragStart($event, project)"
                                    @dragend="handleDragEnd"
                                >
                                    <GripVertical class="size-4" />
                                </div>
                            </TableCell>

                            <TableCell class="p-3">
                                <div
                                    class="ring-border size-4 rounded-full ring-1"
                                    :style="`background-color: ${project.color || 'var(--primary)'}`"
                                ></div>
                            </TableCell>

                            <!-- Project Name (+ inline status/priority on mobile) -->
                            <TableCell class="font-medium">
                                <Link
                                    :href="route('projects.show', project.id)"
                                    prefetch
                                    class="text-foreground hover:text-primary focus-visible:ring-ring/50 rounded-sm font-semibold transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                    @click.stop
                                >
                                    {{ project.name }}
                                </Link>
                                <div class="mt-1 flex flex-wrap items-center gap-2 md:hidden">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="statusBadgeClass(project.status)"
                                    >
                                        {{ labelize(project.status) }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-medium sm:hidden"
                                        :class="priorityMeta(project.priority).text"
                                    >
                                        <span class="size-1.5 rounded-full" :class="priorityMeta(project.priority).dot"></span>
                                        {{ labelize(project.priority) }}
                                    </span>
                                    <span v-if="project.completion_percentage !== undefined" class="text-muted-foreground text-xs tabular-nums">
                                        {{ project.completion_percentage }}%
                                    </span>
                                </div>
                            </TableCell>

                            <!-- Description -->
                            <TableCell class="hidden max-w-xs lg:table-cell">
                                <span class="text-muted-foreground line-clamp-2">
                                    {{ project.description || 'No description' }}
                                </span>
                            </TableCell>

                            <!-- Status -->
                            <TableCell class="hidden md:table-cell">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusBadgeClass(project.status)"
                                >
                                    {{ labelize(project.status) }}
                                </span>
                            </TableCell>

                            <!-- Priority -->
                            <TableCell class="hidden sm:table-cell">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="priorityMeta(project.priority).text">
                                    <span class="size-1.5 rounded-full" :class="priorityMeta(project.priority).dot"></span>
                                    {{ labelize(project.priority) }}
                                </span>
                            </TableCell>

                            <!-- Progress -->
                            <TableCell class="hidden md:table-cell">
                                <div v-if="project.completion_percentage !== undefined" class="min-w-24">
                                    <div class="flex items-center gap-2">
                                        <div class="bg-muted h-1.5 flex-1 overflow-hidden rounded-full">
                                            <div
                                                class="bg-primary h-full rounded-full transition-all duration-300"
                                                :style="{ width: project.completion_percentage + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-muted-foreground min-w-8 text-right text-xs font-medium tabular-nums">
                                            {{ project.completion_percentage }}%
                                        </span>
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground text-sm">-</span>
                            </TableCell>

                            <!-- Actions -->
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-0.5 sm:gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Edit project"
                                        @click.stop="openEditModal(project)"
                                        class="text-muted-foreground hover:text-primary size-8 transition-colors duration-150 sm:hidden"
                                    >
                                        <Edit class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click.stop="openEditModal(project)"
                                        class="text-muted-foreground hover:text-primary hidden transition-colors duration-150 sm:inline-flex"
                                    >
                                        <Edit class="size-4" />
                                        Edit
                                    </Button>
                                    <Button asChild variant="outline" size="sm" class="hidden sm:inline-flex">
                                        <Link :href="route('projects.show', project.id)" @click.stop>
                                            <Eye class="size-4" />
                                            View
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Delete project"
                                        @click.stop="openDeleteDialog(project)"
                                        class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive size-8 transition-colors duration-150"
                                    >
                                        <Trash2 class="size-4" />
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
                        <AlertDialogTitle>Delete project</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{{ projectToDelete?.name }}"? This cannot be undone and will permanently remove the
                            project and all of its tasks.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="isDeleting" @click="cancelDelete">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            :disabled="isDeleting"
                            @click="confirmDelete"
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            <Loader2 v-if="isDeleting" class="size-4 animate-spin" />
                            {{ isDeleting ? 'Deleting…' : 'Delete project' }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </PageContainer>
    </AppLayout>
</template>
