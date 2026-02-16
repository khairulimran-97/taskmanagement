<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Plus,
    ChevronDown,
    ChevronRight as ChevronRightIcon,
    MoreVertical,
    CheckCircle2,
    Trash2,
    Eye,
    Edit,
    ChevronUp,
    ChevronsUp,
    ChevronLeft,
    ChevronRight,
    Circle,
    Clock,
    XCircle,
    CalendarDays,
    ListTree,
    ArrowUpToLine,
    ArrowUp,
    ArrowDown,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';

const TASKS_PER_PAGE = 15;

const props = defineProps({
    project: { type: Object, required: true },
    updatingTasks: { type: Object, required: true },
    searchQuery: { type: String, default: '' },
    filterPriority: { type: String, default: 'all' },
});

const emit = defineEmits(['add-task', 'view-task', 'toggle-task', 'delete-task', 'view-tags', 'edit-task', 'reorder-tasks']);

// Collapsed sections
const collapsedSections = ref<Record<string, boolean>>({});
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

const tasksByStatus = computed(() => ({
    todo: filteredRootTasks.value.filter((t: any) => t.status === 'todo').sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
    in_progress: filteredRootTasks.value.filter((t: any) => t.status === 'in_progress').sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
    completed: filteredRootTasks.value.filter((t: any) => t.status === 'completed').sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
    cancelled: filteredRootTasks.value.filter((t: any) => t.status === 'cancelled').sort((a: any, b: any) => (a.sort_order || 0) - (b.sort_order || 0)),
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
const goToPage = (key: string, page: number) => { currentPage.value[key] = Math.max(1, Math.min(page, totalPages(key))); };

// Subtask count
const subtaskCountMap = computed<Record<string | number, number>>(() => {
    const map: Record<string | number, number> = {};
    (props.project.tasks || []).forEach((t: any) => { if (t.parent_task_id != null) map[t.parent_task_id] = (map[t.parent_task_id] || 0) + 1; });
    return map;
});

// Status sections
const statusSections = computed(() => [
    { key: 'todo', label: 'Todo', icon: Circle, iconClass: 'text-gray-400', tasks: tasksByStatus.value.todo || [] },
    { key: 'in_progress', label: 'In Progress', icon: Clock, iconClass: 'text-amber-500', tasks: tasksByStatus.value.in_progress || [] },
    { key: 'completed', label: 'Done', icon: CheckCircle2, iconClass: 'text-green-500', tasks: tasksByStatus.value.completed || [] },
    { key: 'cancelled', label: 'Cancelled', icon: XCircle, iconClass: 'text-red-400', tasks: tasksByStatus.value.cancelled || [] },
].filter(s => s.tasks.length > 0));

// Priority
const getPriorityBadge = (p: string) => {
    const c: Record<string, { class: string; dot: string }> = {
        urgent: { class: 'text-red-700 dark:text-red-400', dot: 'bg-red-500' },
        high: { class: 'text-orange-700 dark:text-orange-400', dot: 'bg-orange-500' },
        medium: { class: 'text-yellow-700 dark:text-yellow-400', dot: 'bg-yellow-500' },
        low: { class: 'text-green-700 dark:text-green-400', dot: 'bg-green-500' },
    };
    return c[p] || { class: 'text-gray-500', dot: 'bg-gray-400' };
};

// Date formatting & color
const formatDate = (date: string | null) => {
    if (!date) return null;
    return new Date(date).toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
};
const getDueDateClass = (task: any) => {
    if (!task.due_date || task.status === 'completed') return 'text-muted-foreground';
    const diff = Math.ceil((new Date(task.due_date).getTime() - Date.now()) / 86400000);
    if (diff < 0) return 'text-red-600 dark:text-red-400';
    if (diff <= 1) return 'text-orange-600 dark:text-orange-400';
    if (diff <= 3) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-muted-foreground';
};

// Reorder
const moveTaskUp = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    if (i <= 0) return;
    emit('reorder-tasks', [{ id: task.id, sort_order: tasks[i - 1].sort_order }, { id: tasks[i - 1].id, sort_order: task.sort_order }]);
};
const moveTaskDown = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    if (i >= tasks.length - 1) return;
    emit('reorder-tasks', [{ id: task.id, sort_order: tasks[i + 1].sort_order }, { id: tasks[i + 1].id, sort_order: task.sort_order }]);
};
const moveTaskToTop = (task: any, tasks: any[]) => {
    const i = tasks.findIndex((t: any) => t.id === task.id);
    if (i <= 0) return;
    const updates = tasks.slice(0, i).map((t: any, idx: number) => ({ id: t.id, sort_order: idx + 1 }));
    updates.push({ id: task.id, sort_order: 0 });
    emit('reorder-tasks', updates);
};
const canMoveUp = (task: any, tasks: any[]) => tasks.findIndex((t: any) => t.id === task.id) > 0;
const canMoveDown = (task: any, tasks: any[]) => { const i = tasks.findIndex((t: any) => t.id === task.id); return i < tasks.length - 1; };
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <div class="space-y-5">
            <div v-for="section in statusSections" :key="section.key"
                 class="overflow-hidden rounded-lg border border-border bg-card shadow-sm">

                <!-- Section Header -->
                <button
                    @click="toggleSection(section.key)"
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/50"
                >
                    <div class="flex items-center gap-2.5">
                        <ChevronRightIcon
                            class="h-4 w-4 text-muted-foreground transition-transform duration-200"
                            :class="{ 'rotate-90': !collapsedSections[section.key] }"
                        />
                        <component :is="section.icon" class="h-4 w-4" :class="section.iconClass" />
                        <span class="text-sm font-semibold text-foreground">{{ section.label }}</span>
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground">
                            {{ section.tasks.length }}
                        </span>
                    </div>
                </button>

                <!-- Table -->
                <div v-if="!collapsedSections[section.key]" class="border-t border-border">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-muted/30 hover:bg-muted/30">
                                <TableHead class="w-10 pl-4 pr-0"></TableHead>
                                <TableHead class="min-w-[200px]">Task</TableHead>
                                <TableHead class="hidden w-32 md:table-cell">Due Date</TableHead>
                                <TableHead class="hidden w-28 sm:table-cell">Priority</TableHead>
                                <TableHead class="hidden w-48 lg:table-cell">Tags</TableHead>
                                <TableHead class="w-12 pr-4 text-right"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="task in paginatedTasks(section.key)"
                                :key="task.id"
                                class="group cursor-pointer transition-colors"
                                @click="$emit('view-task', task)"
                            >
                                <!-- Checkbox -->
                                <TableCell class="w-10 pl-4 pr-0" @click.stop>
                                    <Checkbox
                                        :checked="task.status === 'completed'"
                                        @click="$emit('toggle-task', task)"
                                        :disabled="updatingTasks.has(task.id)"
                                    />
                                </TableCell>

                                <!-- Task Name + subtask indicator -->
                                <TableCell class="min-w-[200px] max-w-[1px]">
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
                                                <span class="inline-flex flex-shrink-0 items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                    <ListTree class="h-3 w-3" />
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
                                    <span v-if="task.due_date" class="inline-flex items-center gap-1.5 text-xs" :class="getDueDateClass(task)">
                                        <CalendarDays class="h-3.5 w-3.5" />
                                        {{ formatDate(task.due_date) }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </TableCell>

                                <!-- Priority -->
                                <TableCell class="hidden w-28 sm:table-cell" @click.stop>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="getPriorityBadge(task.priority).class">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="getPriorityBadge(task.priority).dot"></span>
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
                                        <span
                                            v-if="task.tags.length > 2"
                                            class="inline-flex cursor-pointer items-center rounded-md bg-muted px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted/80"
                                            @click="$emit('view-tags', task)"
                                        >
                                            +{{ task.tags.length - 2 }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </TableCell>

                                <!-- Actions -->
                                <TableCell class="w-12 pr-4 text-right" @click.stop>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm" class="h-7 w-7 p-0 opacity-0 group-hover:opacity-100 data-[state=open]:opacity-100">
                                                <MoreVertical class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-44">
                                            <DropdownMenuItem @click="$emit('view-task', task)">
                                                <Eye class="mr-2 h-4 w-4" />
                                                View Details
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="$emit('edit-task', task)">
                                                <Edit class="mr-2 h-4 w-4" />
                                                Edit Task
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click="moveTaskToTop(task, section.tasks)"
                                                :disabled="!canMoveUp(task, section.tasks)"
                                            >
                                                <ArrowUpToLine class="mr-2 h-4 w-4" />
                                                Move to Top
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="moveTaskUp(task, section.tasks)"
                                                :disabled="!canMoveUp(task, section.tasks)"
                                            >
                                                <ArrowUp class="mr-2 h-4 w-4" />
                                                Move Up
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="moveTaskDown(task, section.tasks)"
                                                :disabled="!canMoveDown(task, section.tasks)"
                                            >
                                                <ArrowDown class="mr-2 h-4 w-4" />
                                                Move Down
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="$emit('delete-task', task)" class="text-red-600 dark:text-red-400">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div v-if="totalPages(section.key) > 1" class="flex items-center justify-between border-t border-border bg-muted/30 px-4 py-2.5">
                        <span class="text-xs text-muted-foreground">
                            Showing {{ (currentPage[section.key] - 1) * TASKS_PER_PAGE + 1 }}–{{ Math.min(currentPage[section.key] * TASKS_PER_PAGE, section.tasks.length) }} of {{ section.tasks.length }}
                        </span>
                        <div class="flex items-center gap-1">
                            <Button variant="outline" size="sm" class="h-7 w-7 p-0" :disabled="currentPage[section.key] <= 1" @click="goToPage(section.key, currentPage[section.key] - 1)">
                                <ChevronLeft class="h-3.5 w-3.5" />
                            </Button>
                            <span class="px-2 text-xs text-muted-foreground">{{ currentPage[section.key] }} / {{ totalPages(section.key) }}</span>
                            <Button variant="outline" size="sm" class="h-7 w-7 p-0" :disabled="currentPage[section.key] >= totalPages(section.key)" @click="goToPage(section.key, currentPage[section.key] + 1)">
                                <ChevronRight class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <Card v-if="statusSections.length === 0" class="border-dashed">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                        <CheckCircle2 class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <h3 class="text-sm font-medium text-foreground">No tasks found</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Get started by creating your first task</p>
                    <Button @click="$emit('add-task')" size="sm" class="mt-4">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Task
                    </Button>
                </CardContent>
            </Card>
        </div>
    </TooltipProvider>
</template>
