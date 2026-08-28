<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { labelize } from '@/lib/projectMeta';
import { CalendarDays, CheckCircle2, ChevronDown, ChevronsUp, ChevronUp, Edit, Loader2, Plus, TagIcon, Trash2, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    project: { type: Object, required: true },
    isOpen: { type: Boolean, default: false },
    selectedTask: { type: Object, default: null },
    statusConfig: { type: Object, required: true },
    priorityConfig: { type: Object, required: true },
    updatingTasks: { type: Object, required: true },
});

const emit = defineEmits(['close', 'toggle-task', 'edit-task', 'edit-subtask', 'delete-task', 'add-subtask', 'reorder-tasks']);

// Shared status language (tokens only) — identical to list, kanban, and index
const statusBadgeClass = (status: string) => {
    const map: Record<string, string> = {
        todo: 'bg-muted text-muted-foreground',
        in_progress: 'bg-primary/10 text-primary',
        completed: 'bg-success/10 text-success',
        cancelled: 'bg-destructive/10 text-destructive',
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

const getSubtasks = (parentId: string | number) => {
    return (props.project.tasks || []).filter((task) => task.parent_task_id === parentId).sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));
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
    if (diffDays <= 3) return 'text-warning';
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
            <div v-if="isOpen && selectedTask" class="bg-foreground/25 fixed inset-0 z-40" @click="emit('close')"></div>
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
                class="border-border bg-card fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col border-l shadow-md"
            >
                <!-- Header -->
                <div class="border-border flex items-center justify-between gap-3 border-b px-5 py-3.5">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="size-2.5 shrink-0 rounded-full" :style="`background-color: ${project.color}`"></span>
                        <span class="text-muted-foreground truncate text-sm font-medium">{{ project.name }}</span>
                        <span class="text-muted-foreground/50">/</span>
                        <span class="text-muted-foreground text-sm">Task</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button variant="ghost" size="sm" class="h-8 gap-1.5 px-2.5 text-xs" @click="emit('edit-task', selectedTask)">
                            <Edit class="size-3.5" /> Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive size-8"
                            aria-label="Delete task"
                            @click="emit('delete-task', selectedTask)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                        <span class="bg-border mx-1 h-5 w-px"></span>
                        <Button variant="ghost" size="icon" class="size-8" aria-label="Close panel" @click="emit('close')">
                            <X class="size-4" />
                        </Button>
                    </div>
                </div>

                <!-- Scroll body -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Task hero -->
                    <div class="border-border border-b px-5 py-5">
                        <div class="flex items-start gap-3">
                            <button
                                type="button"
                                :aria-label="isMainCompleted ? 'Mark task as not completed' : 'Mark task as completed'"
                                @click="emit('toggle-task', selectedTask)"
                                :disabled="updatingTasks.has(selectedTask.id)"
                                class="focus-visible:ring-ring/50 mt-0.5 flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-full border-2 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                :class="
                                    isMainCompleted
                                        ? 'border-success bg-success text-success-foreground'
                                        : 'border-muted-foreground/40 hover:border-primary text-transparent'
                                "
                            >
                                <Loader2 v-if="updatingTasks.has(selectedTask.id)" class="text-muted-foreground size-3.5 animate-spin" />
                                <CheckCircle2 v-else class="size-4" />
                            </button>

                            <div class="min-w-0 flex-1">
                                <h2
                                    class="text-xl leading-snug font-semibold tracking-tight"
                                    :class="isMainCompleted ? 'text-muted-foreground line-through' : 'text-foreground'"
                                >
                                    {{ selectedTask.title }}
                                </h2>
                                <p v-if="selectedTask.description" class="text-muted-foreground mt-1.5 text-sm leading-relaxed break-words">
                                    {{ selectedTask.description }}
                                </p>
                            </div>
                        </div>

                        <!-- Status + priority -->
                        <div class="mt-4 flex flex-wrap items-center gap-3 pl-9">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="statusBadgeClass(selectedTask.status)"
                            >
                                {{ statusConfig[selectedTask.status]?.label || labelize(selectedTask.status) }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 text-xs font-medium"
                                :class="priorityMeta(selectedTask.priority || 'medium').text"
                            >
                                <span class="size-1.5 rounded-full" :class="priorityMeta(selectedTask.priority || 'medium').dot"></span>
                                {{ labelize(selectedTask.priority || 'medium') }} priority
                            </span>
                        </div>
                    </div>

                    <!-- Meta -->
                    <div class="border-border bg-border grid grid-cols-2 gap-px border-b">
                        <div class="bg-card px-5 py-3">
                            <p class="text-muted-foreground mb-1 flex items-center gap-1.5 text-xs"><CalendarDays class="size-3" /> Start date</p>
                            <p class="text-foreground text-sm font-medium tabular-nums">{{ formatDate(selectedTask.start_date) }}</p>
                        </div>
                        <div class="bg-card px-5 py-3">
                            <p class="text-muted-foreground mb-1 flex items-center gap-1.5 text-xs"><CalendarDays class="size-3" /> Due date</p>
                            <p class="text-sm font-medium tabular-nums" :class="getDueDateClass(selectedTask)">
                                {{ formatDate(selectedTask.due_date) }}
                            </p>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="selectedTask.tags && selectedTask.tags.length > 0" class="border-border border-b px-5 py-4">
                        <p class="text-muted-foreground mb-2 flex items-center gap-1.5 text-xs"><TagIcon class="size-3" /> Tags</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in selectedTask.tags"
                                :key="tag.id"
                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium"
                                :style="`border-color: ${tag.color}66; color: ${tag.color}; background-color: ${tag.color}14`"
                            >
                                {{ tag.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Subtasks -->
                    <div class="px-5 py-5">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-foreground text-sm font-semibold">Subtasks</h3>
                                <span
                                    v-if="subtasks.length"
                                    class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                >
                                    {{ completedCount }}/{{ subtasks.length }}
                                </span>
                            </div>
                            <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="emit('add-subtask', selectedTask)">
                                <Plus class="size-3" /> Add
                            </Button>
                        </div>

                        <!-- Progress -->
                        <div v-if="subtasks.length" class="mb-4 flex items-center gap-3">
                            <div class="bg-muted h-1.5 flex-1 overflow-hidden rounded-full">
                                <div class="bg-primary h-full rounded-full transition-all duration-300" :style="`width: ${subtaskProgress}%`"></div>
                            </div>
                            <span class="text-muted-foreground text-xs font-medium tabular-nums">{{ subtaskProgress }}%</span>
                        </div>

                        <!-- Subtask form slot -->
                        <slot name="subtask-form"></slot>

                        <!-- Subtask list -->
                        <div v-if="subtasks.length > 0" class="border-border overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/50 hover:bg-muted/50 h-9">
                                        <TableHead class="w-10 py-2"></TableHead>
                                        <TableHead class="text-muted-foreground py-2 text-xs font-medium">Title</TableHead>
                                        <TableHead class="text-muted-foreground w-24 py-2 text-xs font-medium">Status</TableHead>
                                        <TableHead class="text-muted-foreground w-20 py-2 text-xs font-medium">Due</TableHead>
                                        <TableHead class="text-muted-foreground w-16 py-2 text-xs font-medium">Order</TableHead>
                                        <TableHead class="w-16 py-2 text-right text-xs"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="subtask in subtasks"
                                        :key="subtask.id"
                                        class="group hover:bg-muted/40 h-10 transition-colors duration-150"
                                    >
                                        <TableCell class="w-10 py-1">
                                            <button
                                                type="button"
                                                :aria-label="
                                                    subtask.status === 'completed' ? 'Mark subtask as not completed' : 'Mark subtask as completed'
                                                "
                                                @click="emit('toggle-task', subtask)"
                                                :disabled="updatingTasks.has(subtask.id)"
                                                class="focus-visible:ring-ring/50 flex size-6 cursor-pointer items-center justify-center rounded-full border-2 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="
                                                    subtask.status === 'completed'
                                                        ? 'border-success bg-success text-success-foreground'
                                                        : 'border-muted-foreground/40 hover:border-primary text-transparent'
                                                "
                                            >
                                                <Loader2 v-if="updatingTasks.has(subtask.id)" class="text-muted-foreground size-3 animate-spin" />
                                                <CheckCircle2 v-else class="size-3.5" />
                                            </button>
                                        </TableCell>
                                        <TableCell class="py-1">
                                            <p
                                                class="text-xs font-medium"
                                                :class="subtask.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground'"
                                            >
                                                {{ subtask.title }}
                                            </p>
                                            <p v-if="subtask.description" class="text-muted-foreground line-clamp-1 text-xs">
                                                {{ subtask.description }}
                                            </p>
                                        </TableCell>
                                        <TableCell class="w-24 py-1">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="statusBadgeClass(subtask.status)"
                                            >
                                                {{ statusConfig[subtask.status]?.label || labelize(subtask.status) }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="w-20 py-1">
                                            <span v-if="subtask.due_date" class="text-xs tabular-nums" :class="getDueDateClass(subtask)">{{
                                                formatDate(subtask.due_date)
                                            }}</span>
                                            <span v-else class="text-muted-foreground text-xs">—</span>
                                        </TableCell>
                                        <TableCell class="w-16 py-1">
                                            <div
                                                class="flex items-center justify-center gap-0.5 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100"
                                            >
                                                <button
                                                    type="button"
                                                    aria-label="Move subtask to top"
                                                    @click="moveTaskToTop(subtask)"
                                                    :disabled="!canMoveUp(subtask)"
                                                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 flex size-6 cursor-pointer items-center justify-center rounded transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-30"
                                                >
                                                    <ChevronsUp class="size-3" />
                                                </button>
                                                <button
                                                    type="button"
                                                    aria-label="Move subtask up"
                                                    @click="moveTaskUp(subtask)"
                                                    :disabled="!canMoveUp(subtask)"
                                                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 flex size-6 cursor-pointer items-center justify-center rounded transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-30"
                                                >
                                                    <ChevronUp class="size-3" />
                                                </button>
                                                <button
                                                    type="button"
                                                    aria-label="Move subtask down"
                                                    @click="moveTaskDown(subtask)"
                                                    :disabled="!canMoveDown(subtask)"
                                                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 flex size-6 cursor-pointer items-center justify-center rounded transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-30"
                                                >
                                                    <ChevronDown class="size-3" />
                                                </button>
                                            </div>
                                        </TableCell>
                                        <TableCell class="w-16 py-1 text-right">
                                            <div
                                                class="flex items-center justify-end gap-0.5 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100"
                                            >
                                                <button
                                                    type="button"
                                                    aria-label="Edit subtask"
                                                    @click="emit('edit-subtask', subtask)"
                                                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 flex size-6 cursor-pointer items-center justify-center rounded transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                                >
                                                    <Edit class="size-3" />
                                                </button>
                                                <button
                                                    type="button"
                                                    aria-label="Delete subtask"
                                                    @click="emit('delete-task', subtask)"
                                                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-ring/50 flex size-6 cursor-pointer items-center justify-center rounded transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                                >
                                                    <Trash2 class="size-3" />
                                                </button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <!-- Empty -->
                        <div v-else class="border-border bg-muted/30 rounded-lg border border-dashed py-8 text-center">
                            <p class="text-muted-foreground text-sm">No subtasks yet</p>
                            <Button variant="outline" size="sm" @click="emit('add-subtask', selectedTask)" class="mt-3 h-7 px-3 text-xs">
                                <Plus class="size-3" /> Add first subtask
                            </Button>
                        </div>
                    </div>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
