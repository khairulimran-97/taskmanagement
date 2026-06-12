<script setup lang="ts">
import { computed } from 'vue';
import {
    X,
    TagIcon,
    CheckCircle2,
    Circle,
    Edit,
    Trash2,
    Plus,
    Loader2,
    ChevronUp,
    ChevronDown,
    ChevronsUp,
    CalendarDays,
    Flag,
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
import { taskStatusBadge, priorityBadge, labelize, progressColor } from '@/lib/projectMeta';

const props = defineProps({
    project: { type: Object, required: true },
    isOpen: { type: Boolean, default: false },
    selectedTask: { type: Object, default: null },
    statusConfig: { type: Object, required: true },
    priorityConfig: { type: Object, required: true },
    updatingTasks: { type: Object, required: true },
});

const emit = defineEmits([
    'close',
    'toggle-task',
    'edit-task',
    'edit-subtask',
    'delete-task',
    'add-subtask',
    'reorder-tasks',
]);

const getSubtasks = (parentId: string | number) => {
    return (props.project.tasks || [])
        .filter((task) => task.parent_task_id === parentId)
        .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
};

const subtasks = computed(() => (props.selectedTask ? getSubtasks(props.selectedTask.id) : []));
const subtaskProgress = computed(() => {
    const list = subtasks.value;
    if (!list.length) return 0;
    return Math.round((list.filter((t) => t.status === 'completed').length / list.length) * 100);
});
const completedCount = computed(() => subtasks.value.filter((t) => t.status === 'completed').length);

const isMainCompleted = computed(() => props.selectedTask?.status === 'completed');

const formatDate = (date: string | Date | null) => {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
};

const getDueDateClass = (task: any) => {
    if (!task.due_date || task.status === 'completed') return 'text-muted-foreground';
    const due = new Date(task.due_date);
    const now = new Date();
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'text-destructive font-medium';
    if (diffDays <= 1) return 'text-orange-600 dark:text-orange-400 font-medium';
    if (diffDays <= 3) return 'text-amber-600 dark:text-amber-400';
    return 'text-muted-foreground';
};

// Reorder helpers
const moveTaskUp = (task: any) => {
    const list = getSubtasks(props.selectedTask.id);
    const i = list.findIndex((t) => t.id === task.id);
    if (i <= 0) return;
    emit('reorder-tasks', [
        { id: task.id, sort_order: list[i - 1].sort_order },
        { id: list[i - 1].id, sort_order: task.sort_order },
    ]);
};
const moveTaskDown = (task: any) => {
    const list = getSubtasks(props.selectedTask.id);
    const i = list.findIndex((t) => t.id === task.id);
    if (i >= list.length - 1) return;
    emit('reorder-tasks', [
        { id: task.id, sort_order: list[i + 1].sort_order },
        { id: list[i + 1].id, sort_order: task.sort_order },
    ]);
};
const moveTaskToTop = (task: any) => {
    const list = getSubtasks(props.selectedTask.id);
    const i = list.findIndex((t) => t.id === task.id);
    if (i <= 0) return;
    const updates = list.slice(0, i).map((t, index) => ({ id: t.id, sort_order: index + 1 }));
    updates.push({ id: task.id, sort_order: 0 });
    emit('reorder-tasks', updates);
};
const canMoveUp = (task: any) => {
    const list = getSubtasks(props.selectedTask.id);
    return list.findIndex((t) => t.id === task.id) > 0;
};
const canMoveDown = (task: any) => {
    const list = getSubtasks(props.selectedTask.id);
    return list.findIndex((t) => t.id === task.id) < list.length - 1;
};
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isOpen && selectedTask"
                class="fixed inset-0 z-40 bg-foreground/20 backdrop-blur-sm"
                @click="emit('close')"
            ></div>
        </Transition>

        <!-- Panel -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="isOpen && selectedTask"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col bg-card shadow-2xl"
            >
                <!-- Header -->
                <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3.5">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background-color: ${project.color}`"></span>
                        <span class="truncate text-sm font-medium text-muted-foreground">{{ project.name }}</span>
                        <span class="text-muted-foreground/50">/</span>
                        <span class="text-sm text-muted-foreground">Task</span>
                    </div>
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="emit('close')">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Scroll body -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Task hero -->
                    <div class="border-b border-border px-5 py-5">
                        <div class="flex items-start gap-3">
                            <button
                                @click="emit('toggle-task', selectedTask)"
                                :disabled="updatingTasks.has(selectedTask.id)"
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 transition-all hover:scale-105"
                                :class="isMainCompleted ? 'border-[hsl(150_24%_45%)] bg-[hsl(150_24%_45%)] text-white' : 'border-muted-foreground/40 text-transparent hover:border-primary'"
                            >
                                <Loader2 v-if="updatingTasks.has(selectedTask.id)" class="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                                <CheckCircle2 v-else class="h-4 w-4" />
                            </button>

                            <div class="min-w-0 flex-1">
                                <h2
                                    class="font-display text-xl font-semibold leading-snug tracking-tight"
                                    :class="isMainCompleted ? 'text-muted-foreground line-through' : 'text-foreground'"
                                >
                                    {{ selectedTask.title }}
                                </h2>
                                <p v-if="selectedTask.description" class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                    {{ selectedTask.description }}
                                </p>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="mt-4 flex flex-wrap items-center gap-2 pl-9">
                            <Badge variant="outline" :class="taskStatusBadge(selectedTask.status)" class="font-medium">
                                {{ statusConfig[selectedTask.status]?.label || labelize(selectedTask.status) }}
                            </Badge>
                            <Badge variant="outline" :class="priorityBadge(selectedTask.priority || 'medium')" class="font-medium">
                                <Flag class="mr-1 h-3 w-3" />
                                {{ labelize(selectedTask.priority || 'medium') }}
                            </Badge>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 flex items-center gap-2 pl-9">
                            <Button variant="outline" size="sm" @click="emit('edit-task', selectedTask)">
                                <Edit class="mr-1.5 h-3.5 w-3.5" /> Edit
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                @click="emit('delete-task', selectedTask)"
                            >
                                <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Delete
                            </Button>
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="grid grid-cols-2 gap-px border-b border-border bg-border">
                        <div class="bg-card px-5 py-3">
                            <p class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays class="h-3 w-3" /> Start date
                            </p>
                            <p class="text-sm font-medium text-foreground">{{ formatDate(selectedTask.start_date) }}</p>
                        </div>
                        <div class="bg-card px-5 py-3">
                            <p class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                <CalendarDays class="h-3 w-3" /> Due date
                            </p>
                            <p class="text-sm font-medium" :class="getDueDateClass(selectedTask)">{{ formatDate(selectedTask.due_date) }}</p>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="selectedTask.tags && selectedTask.tags.length > 0" class="border-b border-border px-5 py-4">
                        <p class="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <TagIcon class="h-3 w-3" /> Tags
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                v-for="tag in selectedTask.tags"
                                :key="tag.id"
                                variant="outline"
                                class="px-2 py-0.5 text-xs"
                                :style="`border-color: ${tag.color}66; color: ${tag.color}; background-color: ${tag.color}14`"
                            >
                                {{ tag.name }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Subtasks -->
                    <div class="px-5 py-5">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-foreground">Subtasks</h3>
                                <span v-if="subtasks.length" class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                    {{ completedCount }}/{{ subtasks.length }}
                                </span>
                            </div>
                            <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="emit('add-subtask', selectedTask)">
                                <Plus class="mr-1 h-3 w-3" /> Add
                            </Button>
                        </div>

                        <!-- Progress -->
                        <div v-if="subtasks.length" class="mb-4 flex items-center gap-3">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full transition-all duration-500" :class="progressColor(subtaskProgress)" :style="`width: ${subtaskProgress}%`"></div>
                            </div>
                            <span class="text-xs font-medium text-muted-foreground">{{ subtaskProgress }}%</span>
                        </div>

                        <!-- Subtask form slot -->
                        <slot name="subtask-form"></slot>

                        <!-- Subtask list -->
                        <div v-if="subtasks.length > 0" class="overflow-hidden rounded-lg border border-border">
                            <Table>
                                <TableHeader>
                                    <TableRow class="h-9 bg-muted/50 hover:bg-muted/50">
                                        <TableHead class="w-10 py-2"></TableHead>
                                        <TableHead class="py-2 text-xs">Title</TableHead>
                                        <TableHead class="w-24 py-2 text-xs">Status</TableHead>
                                        <TableHead class="w-20 py-2 text-xs">Due</TableHead>
                                        <TableHead class="w-14 py-2 text-xs">Order</TableHead>
                                        <TableHead class="w-16 py-2 text-right text-xs"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="subtask in subtasks" :key="subtask.id" class="group h-10 hover:bg-muted/40">
                                        <TableCell class="w-10 py-1">
                                            <button
                                                @click="emit('toggle-task', subtask)"
                                                :disabled="updatingTasks.has(subtask.id)"
                                                class="flex h-5 w-5 items-center justify-center rounded-full border-2 transition-all hover:scale-110"
                                                :class="subtask.status === 'completed' ? 'border-[hsl(150_24%_45%)] bg-[hsl(150_24%_45%)] text-white' : 'border-muted-foreground/40 text-transparent hover:border-primary'"
                                            >
                                                <Loader2 v-if="updatingTasks.has(subtask.id)" class="h-3 w-3 animate-spin text-muted-foreground" />
                                                <CheckCircle2 v-else class="h-3 w-3" />
                                            </button>
                                        </TableCell>
                                        <TableCell class="py-1">
                                            <p
                                                class="text-xs font-medium"
                                                :class="subtask.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground'"
                                            >
                                                {{ subtask.title }}
                                            </p>
                                            <p v-if="subtask.description" class="line-clamp-1 text-xs text-muted-foreground">
                                                {{ subtask.description }}
                                            </p>
                                        </TableCell>
                                        <TableCell class="w-24 py-1">
                                            <Badge variant="outline" :class="taskStatusBadge(subtask.status)" class="px-1.5 py-0.5 text-xs">
                                                {{ statusConfig[subtask.status]?.label || labelize(subtask.status) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="w-20 py-1">
                                            <span v-if="subtask.due_date" class="text-xs" :class="getDueDateClass(subtask)">{{ formatDate(subtask.due_date) }}</span>
                                            <span v-else class="text-xs text-muted-foreground">—</span>
                                        </TableCell>
                                        <TableCell class="w-14 py-1">
                                            <div class="flex items-center justify-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                                <button @click="moveTaskToTop(subtask)" :disabled="!canMoveUp(subtask)" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" title="Move to top">
                                                    <ChevronsUp class="h-3 w-3" :class="canMoveUp(subtask) ? 'text-primary' : 'text-muted-foreground'" />
                                                </button>
                                                <button @click="moveTaskUp(subtask)" :disabled="!canMoveUp(subtask)" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" title="Move up">
                                                    <ChevronUp class="h-3 w-3 text-muted-foreground" />
                                                </button>
                                                <button @click="moveTaskDown(subtask)" :disabled="!canMoveDown(subtask)" class="rounded p-0.5 hover:bg-muted disabled:opacity-30" title="Move down">
                                                    <ChevronDown class="h-3 w-3 text-muted-foreground" />
                                                </button>
                                            </div>
                                        </TableCell>
                                        <TableCell class="w-16 py-1 text-right">
                                            <div class="flex items-center justify-end gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                                <button @click="emit('edit-subtask', subtask)" class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                                                    <Edit class="h-3 w-3" />
                                                </button>
                                                <button @click="emit('delete-task', subtask)" class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive">
                                                    <Trash2 class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Empty -->
                        <div v-else class="rounded-lg border border-dashed border-border py-8 text-center">
                            <p class="text-sm text-muted-foreground">No subtasks yet</p>
                            <Button variant="outline" size="sm" @click="emit('add-subtask', selectedTask)" class="mt-3 h-7 px-3 text-xs">
                                <Plus class="mr-1 h-3 w-3" /> Add first subtask
                            </Button>
                        </div>
                    </div>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
