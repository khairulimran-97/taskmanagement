<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpToLine,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronRight as ChevronRightIcon,
    Circle,
    Clock,
    Edit,
    Eye,
    ListTodo,
    ListTree,
    Loader2,
    MoreVertical,
    Plus,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const TASKS_PER_PAGE = 15;

const props = defineProps({
    project: { type: Object, required: true },
    updatingTasks: { type: Object, required: true },
    searchQuery: { type: String, default: '' },
    filterPriority: { type: String, default: 'all' },
});

const emit = defineEmits(['add-task', 'view-task', 'toggle-task', 'delete-task', 'view-tags', 'edit-task', 'reorder-tasks']);

// Collapsed sections
// Done and Cancelled start collapsed — they're usually reference, not active work
const collapsedSections = ref<Record<string, boolean>>({ completed: true, cancelled: true });
const toggleSection = (key: string) => {
    collapsedSections.value[key] = !collapsedSections.value[key];
};

// Filtering
const filteredRootTasks = computed(() => {
    let tasks = (props.project.tasks || []).filter((task: any) => !task.parent_task_id);
    if (props.searchQuery) {
        const q = props.searchQuery.toLowerCase();
        tasks = tasks.filter((task: any) => task.title.toLowerCase().includes(q) || (task.description && task.description.toLowerCase().includes(q)));
    }
    if (props.filterPriority && props.filterPriority !== 'all') {
        tasks = tasks.filter((task: any) => task.priority === props.filterPriority);
    }
    return tasks;
});

// True when the project has tasks but the current filters hide them all
const hasRootTasks = computed(() => (props.project.tasks || []).some((task: any) => !task.parent_task_id));

const tasksByStatus = computed(() => ({
    todo: filteredRootTasks.value.filter((t: any) => t.status === 'todo').sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
    in_progress: filteredRootTasks.value
        .filter((t: any) => t.status === 'in_progress')
        .sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
    completed: filteredRootTasks.value
        .filter((t: any) => t.status === 'completed')
        .sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
    cancelled: filteredRootTasks.value
        .filter((t: any) => t.status === 'cancelled')
        .sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
}));

// Pagination
const currentPage = ref<Record<string, number>>({ todo: 1, in_progress: 1, completed: 1, cancelled: 1 });
watch([() => props.searchQuery, () => props.filterPriority], () => {
    currentPage.value = { todo: 1, in_progress: 1, completed: 1, cancelled: 1 };
});
const paginatedTasks = (key: string) => {
    const tasks = tasksByStatus.value[key] || [];
    const page = currentPage.value[key] || 1;
    return tasks.slice((page - 1) * TASKS_PER_PAGE, page * TASKS_PER_PAGE);
};
const totalPages = (key: string) => Math.max(1, Math.ceil((tasksByStatus.value[key]?.length || 0) / TASKS_PER_PAGE));
const goToPage = (key: string, page: number) => {
    currentPage.value[key] = Math.max(1, Math.min(page, totalPages(key)));
};

// Subtask count
const subtaskCountMap = computed<Record<string | number, number>>(() => {
    const map: Record<string | number, number> = {};
    (props.project.tasks || []).forEach((t: any) => {
        if (t.parent_task_id != null) map[t.parent_task_id] = (map[t.parent_task_id] || 0) + 1;
    });
    return map;
});

// Status sections — shared status language (tokens only)
const statusSections = computed(() =>
    [
        { key: 'todo', label: 'To do', icon: Circle, iconClass: 'text-muted-foreground', tasks: tasksByStatus.value.todo || [] },
        { key: 'in_progress', label: 'In progress', icon: Clock, iconClass: 'text-primary', tasks: tasksByStatus.value.in_progress || [] },
        { key: 'completed', label: 'Completed', icon: CheckCircle2, iconClass: 'text-success', tasks: tasksByStatus.value.completed || [] },
        { key: 'cancelled', label: 'Cancelled', icon: XCircle, iconClass: 'text-destructive', tasks: tasksByStatus.value.cancelled || [] },
    ].filter((s) => s.tasks.length > 0),
);

// Priority — dot + label, identical across list, kanban, sidebar, and index
const getPriorityBadge = (p: string) => {
    const c: Record<string, { class: string; dot: string }> = {
        urgent: { class: 'text-destructive', dot: 'bg-destructive' },
        high: { class: 'text-warning', dot: 'bg-warning' },
        medium: { class: 'text-warning', dot: 'bg-warning' },
        low: { class: 'text-muted-foreground', dot: 'bg-muted-foreground/50' },
    };
    return c[p] || { class: 'text-muted-foreground', dot: 'bg-muted-foreground/40' };
};

// Date formatting & color
const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
};
const getDueDateClass = (task: any) => {
    if (!task.due_date || task.status === 'completed') return 'text-muted-foreground';
    const diff = Math.ceil((new Date(task.due_date).getTime() - Date.now()) / 86400000);
    if (diff < 0) return 'text-destructive';
    if (diff <= 3) return 'text-warning';
    return 'text-muted-foreground';
};

// Reorder
const moveTaskUp = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    if (i <= 0) return;
    emit('reorder-tasks', [
        { id: task.id, sort_order: tasks[i - 1].sort_order },
        { id: tasks[i - 1].id, sort_order: task.sort_order },
    ]);
};
const moveTaskDown = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    if (i >= tasks.length - 1) return;
    emit('reorder-tasks', [
        { id: task.id, sort_order: tasks[i + 1].sort_order },
        { id: tasks[i + 1].id, sort_order: task.sort_order },
    ]);
};
const moveTaskToTop = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    if (i <= 0) return;
    const updates = tasks.slice(0, i).map((t: any, idx: number) => ({ id: t.id, sort_order: idx + 1 }));
    updates.push({ id: task.id, sort_order: 0 });
    emit('reorder-tasks', updates);
};
const canMoveUp = (task: any, tasks: any[]) => tasks.findIndex((t: any) => t.id === task.id) > 0;
const canMoveDown = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    return i < tasks.length - 1;
};
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <div class="space-y-5">
            <div v-for="section in statusSections" :key="section.key" class="border-border bg-card overflow-hidden rounded-lg border shadow-xs">
                <!-- Section Header -->
                <button
                    type="button"
                    @click="toggleSection(section.key)"
                    :aria-expanded="!collapsedSections[section.key]"
                    class="hover:bg-muted/60 focus-visible:ring-ring/50 flex w-full cursor-pointer items-center justify-between px-4 py-3 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                >
                    <div class="flex items-center gap-2.5">
                        <ChevronRightIcon
                            class="text-muted-foreground size-4 transition-transform duration-200"
                            :class="{ 'rotate-90': !collapsedSections[section.key] }"
                        />
                        <component :is="section.icon" class="size-4" :class="section.iconClass" />
                        <span class="text-foreground text-sm font-semibold">{{ section.label }}</span>
                        <span
                            class="bg-muted text-muted-foreground inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums"
                        >
                            {{ section.tasks.length }}
                        </span>
                    </div>
                </button>

                <!-- Table -->
                <div v-if="!collapsedSections[section.key]" class="border-border border-t">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-muted/30 hover:bg-muted/30">
                                <TableHead class="w-10 pr-0 pl-4"></TableHead>
                                <TableHead class="text-muted-foreground min-w-[200px] text-xs font-medium">Task</TableHead>
                                <TableHead class="text-muted-foreground hidden w-32 text-xs font-medium md:table-cell">Due date</TableHead>
                                <TableHead class="text-muted-foreground hidden w-28 text-xs font-medium sm:table-cell">Priority</TableHead>
                                <TableHead class="text-muted-foreground hidden w-48 text-xs font-medium lg:table-cell">Tags</TableHead>
                                <TableHead class="w-12 pr-4 text-right"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="task in paginatedTasks(section.key)"
                                :key="task.id"
                                class="group hover:bg-muted/60 cursor-pointer transition-colors duration-150"
                                @click="$emit('view-task', task)"
                            >
                                <!-- Checkbox -->
                                <TableCell class="w-10 pr-0 pl-4" @click.stop>
                                    <Loader2 v-if="updatingTasks.has(task.id)" class="text-muted-foreground size-4 animate-spin" />
                                    <Checkbox
                                        v-else
                                        :checked="task.status === 'completed'"
                                        :aria-label="task.status === 'completed' ? 'Mark task as not completed' : 'Mark task as completed'"
                                        @click="$emit('toggle-task', task)"
                                        :disabled="updatingTasks.has(task.id)"
                                    />
                                </TableCell>

                                <!-- Task Name + subtask indicator -->
                                <TableCell class="max-w-[1px] min-w-[200px]">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="truncate text-sm font-medium"
                                            :class="task.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground'"
                                            :title="task.title"
                                        >
                                            {{ task.title }}
                                        </span>
                                        <Tooltip v-if="subtaskCountMap[task.id]">
                                            <TooltipTrigger asChild>
                                                <span
                                                    class="bg-muted text-muted-foreground inline-flex flex-shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-xs tabular-nums"
                                                >
                                                    <ListTree class="size-3" />
                                                    {{ subtaskCountMap[task.id] }}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {{ subtaskCountMap[task.id] }} subtask{{ subtaskCountMap[task.id] > 1 ? 's' : '' }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>

                                <!-- Due Date -->
                                <TableCell class="hidden w-32 md:table-cell">
                                    <span
                                        v-if="task.due_date"
                                        class="inline-flex items-center gap-1.5 text-xs tabular-nums"
                                        :class="getDueDateClass(task)"
                                    >
                                        <CalendarDays class="size-3.5" />
                                        {{ formatDate(task.due_date) }}
                                    </span>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>

                                <!-- Priority -->
                                <TableCell class="hidden w-28 sm:table-cell" @click.stop>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="getPriorityBadge(task.priority).class">
                                        <span class="size-1.5 rounded-full" :class="getPriorityBadge(task.priority).dot"></span>
                                        {{ task.priority.charAt(0).toUpperCase() + task.priority.slice(1) }}
                                    </span>
                                </TableCell>

                                <!-- Tags -->
                                <TableCell class="hidden w-48 lg:table-cell" @click.stop>
                                    <div v-if="task.tags?.length" class="flex flex-wrap gap-1">
                                        <span
                                            v-for="tag in task.tags.slice(0, 2)"
                                            :key="tag.id"
                                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                            :style="`background-color: ${tag.color}10; color: ${tag.color}; --tw-ring-color: ${tag.color}30`"
                                        >
                                            {{ tag.name }}
                                        </span>
                                        <button
                                            v-if="task.tags.length > 2"
                                            type="button"
                                            :aria-label="`Show all ${task.tags.length} tags`"
                                            class="bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground focus-visible:ring-ring/50 inline-flex cursor-pointer items-center rounded-md px-1.5 py-0.5 text-xs tabular-nums transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                            @click="$emit('view-tags', task)"
                                        >
                                            +{{ task.tags.length - 2 }}
                                        </button>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>

                                <!-- Actions -->
                                <TableCell class="w-12 pr-4 text-right" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Task actions"
                                                class="size-8 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 sm:data-[state=open]:opacity-100"
                                            >
                                                <MoreVertical class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-44">
                                            <DropdownMenuItem @click="$emit('view-task', task)">
                                                <Eye class="size-4" />
                                                View details
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="$emit('edit-task', task)">
                                                <Edit class="size-4" />
                                                Edit task
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="moveTaskToTop(task, section.tasks)" :disabled="!canMoveUp(task, section.tasks)">
                                                <ArrowUpToLine class="size-4" />
                                                Move to top
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="moveTaskUp(task, section.tasks)" :disabled="!canMoveUp(task, section.tasks)">
                                                <ArrowUp class="size-4" />
                                                Move up
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="moveTaskDown(task, section.tasks)"
                                                :disabled="!canMoveDown(task, section.tasks)"
                                            >
                                                <ArrowDown class="size-4" />
                                                Move down
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="$emit('delete-task', task)" class="text-destructive focus:text-destructive">
                                                <Trash2 class="size-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div v-if="totalPages(section.key) > 1" class="border-border bg-muted/30 flex items-center justify-between border-t px-4 py-2.5">
                        <span class="text-muted-foreground text-xs tabular-nums">
                            Showing {{ (currentPage[section.key] - 1) * TASKS_PER_PAGE + 1 }}–{{
                                Math.min(currentPage[section.key] * TASKS_PER_PAGE, section.tasks.length)
                            }}
                            of {{ section.tasks.length }}
                        </span>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Previous page"
                                class="size-8"
                                :disabled="currentPage[section.key] <= 1"
                                @click="goToPage(section.key, currentPage[section.key] - 1)"
                            >
                                <ChevronLeft class="size-3.5" />
                            </Button>
                            <span class="text-muted-foreground px-2 text-xs tabular-nums"
                                >{{ currentPage[section.key] }} / {{ totalPages(section.key) }}</span
                            >
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Next page"
                                class="size-8"
                                :disabled="currentPage[section.key] >= totalPages(section.key)"
                                @click="goToPage(section.key, currentPage[section.key] + 1)"
                            >
                                <ChevronRight class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty States -->
            <EmptyState
                v-if="statusSections.length === 0 && hasRootTasks"
                :icon="ListTodo"
                title="No matching tasks"
                description="No tasks match your current search or filters."
            />
            <EmptyState
                v-else-if="statusSections.length === 0"
                :icon="ListTodo"
                title="No tasks yet"
                description="Create your first task to get started."
            >
                <template #action>
                    <Button @click="$emit('add-task')" size="sm">
                        <Plus class="size-4" />
                        Add task
                    </Button>
                </template>
            </EmptyState>
        </div>
    </TooltipProvider>
</template>
