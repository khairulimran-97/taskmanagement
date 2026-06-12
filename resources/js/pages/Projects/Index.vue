<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BreadcrumbItem, Project } from '@/types';
import { Activity, CheckCircle2, Edit, Eye, Flag, FolderKanban, GripVertical, Loader2, Trash2 } from 'lucide-vue-next';
import { projectStatusBadge, priorityBadge, progressColor, labelize } from '@/lib/projectMeta';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
        dragImage.className = 'bg-primary/10 border-2 border-primary/40 rounded-lg p-3 shadow-lg';
        dragImage.innerHTML = `<div class="font-semibold text-primary">📋 ${project.name}</div>`;
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

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Projects" />
        <PageContainer>
            <PageHeader title="Projects" description="Organize your work into projects and track progress.">
                <template #actions>
                    <CreateProjectDialog
                        v-model:open="isCreateModalOpen"
                        @success="handleCreateSuccess"
                    />
                </template>
            </PageHeader>

            <EditProjectDialog
                :open="isEditModalOpen"
                :project="editingProject"
                @update:open="handleEditModalClose"
                @success="handleEditSuccess"
            />

            <div class="mt-6 mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard :icon="FolderKanban" label="Total Projects" :value="props.projects.length" accent />
                <StatCard :icon="Activity" label="Active" :value="props.projects.filter(p => p.status === 'active').length" hint="in progress" />
                <StatCard :icon="CheckCircle2" label="Completed" :value="props.projects.filter(p => p.status === 'completed').length" hint="done" />
                <StatCard :icon="Flag" label="High Priority" :value="props.projects.filter(p => p.priority === 'high').length" hint="needs focus" />
            </div>

            <div class="bg-card rounded-lg border border-border overflow-x-auto">
                <div class="hidden sm:flex px-4 py-3 bg-primary/8 border-b border-border items-center justify-between">
                    <div class="flex items-center text-sm text-primary">
                        <GripVertical class="w-4 h-4 mr-2" />
                        <span class="font-medium">Drag &amp; Drop to Reorder Projects</span>
                    </div>
                    <div v-if="isReordering" class="flex items-center text-sm text-primary">
                        <Loader2 class="w-4 h-4 mr-2 animate-spin" />
                        <span>Saving order...</span>
                    </div>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/50">
                            <TableHead class="hidden sm:table-cell w-8"></TableHead>
                            <TableHead class="w-8"></TableHead>
                            <TableHead class="font-semibold text-foreground">Project Name</TableHead>
                            <TableHead class="hidden lg:table-cell font-semibold text-foreground">Description</TableHead>
                            <TableHead class="hidden md:table-cell font-semibold text-foreground">Status</TableHead>
                            <TableHead class="hidden sm:table-cell font-semibold text-foreground">Priority</TableHead>
                            <TableHead class="hidden md:table-cell font-semibold text-foreground">Progress</TableHead>
                            <TableHead class="text-right font-semibold text-foreground">Actions</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-if="sortedProjects.length === 0">
                            <TableCell :colspan="10" class="text-center py-12 text-muted-foreground">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                                        <GripVertical class="w-8 h-8 text-muted-foreground/60" />
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
                        ? 'opacity-50 scale-[0.99] bg-primary/5 shadow-lg border-2 border-primary/30'
                        : 'hover:bg-muted/50',
                    dragOverIndex === index && draggedProject?.id !== project.id
                        ? 'bg-primary/5 border-l-4 border-primary shadow-sm'
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
                            <TableCell class="hidden sm:table-cell p-3 relative">
                                <div
                                    class="flex items-center justify-center w-6 h-8 rounded transition-colors"
                                    :class="isDragging && draggedProject?.id === project.id
                            ? 'text-primary bg-primary/15'
                            : 'text-muted-foreground/60 group-hover:text-foreground group-hover:bg-muted'"
                                >
                                    <GripVertical class="w-4 h-4" />
                                </div>
                                <div
                                    v-if="dragOverIndex === index && draggedProject?.id !== project.id"
                                    class="absolute top-0 left-2 right-2 h-0.5 bg-primary rounded-full shadow-sm"
                                ></div>
                                <div
                                    v-if="dragOverIndex === index && draggedProject?.id !== project.id"
                                    class="absolute top-0 left-2 w-2 h-2 bg-primary rounded-full transform -translate-y-1 shadow-sm"
                                ></div>
                            </TableCell>

                            <TableCell class="p-3">
                                <div
                                    class="w-4 h-4 rounded-full border-2 border-card shadow-sm ring-1 ring-border"
                                    :style="`background-color: ${project.color || 'var(--primary)'}`"
                                ></div>
                            </TableCell>

                            <!-- Project Name (+ inline status/priority on mobile) -->
                            <TableCell class="font-medium">
                                <Link
                                    :href="route('projects.show', project.id)"
                                    prefetch
                                    class="font-semibold text-foreground transition-colors hover:text-primary"
                                    @click.stop
                                >
                                    {{ project.name }}
                                </Link>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 md:hidden">
                                    <Badge variant="outline" :class="projectStatusBadge(project.status)" class="text-xs font-medium">
                                        {{ labelize(project.status) }}
                                    </Badge>
                                    <Badge variant="outline" :class="priorityBadge(project.priority)" class="text-xs font-medium sm:hidden">
                                        {{ labelize(project.priority) }}
                                    </Badge>
                                    <span v-if="project.completion_percentage !== undefined" class="text-xs text-muted-foreground">
                                        {{ project.completion_percentage }}%
                                    </span>
                                </div>
                            </TableCell>

                            <!-- Description -->
                            <TableCell class="hidden lg:table-cell max-w-xs">
                    <span class="text-muted-foreground line-clamp-2">
                        {{ project.description || 'No description' }}
                    </span>
                            </TableCell>

                            <!-- Status -->
                            <TableCell class="hidden md:table-cell">
                                <Badge variant="outline" :class="projectStatusBadge(project.status)" class="font-medium">
                                    <CheckCircle2 v-if="project.status === 'completed'" class="w-3 h-3 mr-1" />
                                    {{ labelize(project.status) }}
                                </Badge>
                            </TableCell>

                            <!-- Priority -->
                            <TableCell class="hidden sm:table-cell">
                                <Badge variant="outline" :class="priorityBadge(project.priority)" class="font-medium">
                                    {{ labelize(project.priority) }}
                                </Badge>
                            </TableCell>

                            <!-- Progress -->
                            <TableCell class="hidden md:table-cell">
                                <div v-if="project.completion_percentage !== undefined" class="min-w-24">
                                    <div class="flex items-center space-x-2">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="progressColor(project.completion_percentage)"
                                                :style="{ width: project.completion_percentage + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-xs text-muted-foreground font-medium min-w-8 text-right">
                                {{ project.completion_percentage }}%
                            </span>
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground text-sm">-</span>
                            </TableCell>

                            <!-- Actions -->
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-0.5 sm:gap-1">
                                    <Button variant="ghost" size="icon" @click.stop="openEditModal(project)" class="h-8 w-8 text-muted-foreground transition-colors hover:text-primary sm:hidden">
                                        <Edit class="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="sm" @click.stop="openEditModal(project)" class="hidden text-muted-foreground transition-colors hover:text-primary sm:inline-flex">
                                        <Edit class="w-4 h-4 mr-1" />
                                        Edit
                                    </Button>
                                    <Button asChild variant="outline" size="sm" class="hidden sm:inline-flex">
                                        <Link :href="route('projects.show', project.id)" @click.stop>
                                            <Eye class="w-4 h-4 mr-1" />
                                            View
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click.stop="openDeleteDialog(project)"
                                        class="h-8 w-8 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 class="w-4 h-4" />
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
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90 focus:ring-destructive"
                        >
                            Delete Project
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </PageContainer>
    </AppLayout>
</template>
