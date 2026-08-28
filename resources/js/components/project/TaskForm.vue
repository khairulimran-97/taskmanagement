<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DateFormatter, type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';
import { Calendar, Loader2, Plus, X } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    projectId: {
        type: Number,
        required: true,
    },
    editingTask: {
        type: Object,
        default: null,
    },
    availableTags: {
        type: Array,
        default: () => [],
    },
    isSubmitting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:open', 'submit', 'cancel', 'create-tag']);

const df = new DateFormatter('en-US', { dateStyle: 'medium' });
const startDateValue = ref<DateValue>();
const dueDateValue = ref<DateValue>();

// New tag state
const newTagName = ref('');
const isCreatingTag = ref(false);

// Client-side guard: the footer button bypasses native form validation
const titleError = ref('');

// Task form data
const taskForm = reactive({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    due_date: '',
    start_date: '',
    project_id: props.projectId,
    assigned_to: null,
    parent_task_id: null,
    tag_ids: [],
    new_tags: [],
});

const resetForm = () => {
    taskForm.title = '';
    taskForm.description = '';
    taskForm.status = 'todo';
    taskForm.priority = 'medium';
    taskForm.due_date = '';
    taskForm.start_date = '';
    taskForm.assigned_to = null;
    taskForm.parent_task_id = null;
    taskForm.tag_ids = [];
    taskForm.new_tags = [];
    startDateValue.value = undefined;
    dueDateValue.value = undefined;
    titleError.value = '';

    newTagName.value = '';
};

const populateForm = (task) => {
    taskForm.title = task.title || '';
    taskForm.description = task.description || '';
    taskForm.status = task.status || 'todo';
    taskForm.priority = task.priority || 'medium';
    taskForm.due_date = task.due_date || '';
    taskForm.start_date = task.start_date || '';
    taskForm.assigned_to = task.assigned_to || null;
    taskForm.parent_task_id = task.parent_task_id || null;
    taskForm.tag_ids = task.tags?.map((tag) => tag.id) || [];
    titleError.value = '';

    // Set date values if available
    if (task.start_date) {
        try {
            startDateValue.value = parseDate(task.start_date.split('T')[0]);
        } catch {
            startDateValue.value = undefined;
        }
    } else {
        startDateValue.value = undefined;
    }

    if (task.due_date) {
        try {
            dueDateValue.value = parseDate(task.due_date.split('T')[0]);
        } catch {
            dueDateValue.value = undefined;
        }
    } else {
        dueDateValue.value = undefined;
    }
};

watch(
    () => props.editingTask,
    (task) => {
        if (task) {
            populateForm(task);
        } else {
            resetForm();
        }
    },
    { immediate: true },
);

// Reset form when dialog opens for a new task (handles reopening with null editingTask)
watch(
    () => props.isOpen,
    (open) => {
        if (open && !props.editingTask) {
            resetForm();
        }
    },
);

// Watch for date changes
watch(startDateValue, (newValue) => {
    if (newValue) {
        taskForm.start_date = newValue.toString();
    } else {
        taskForm.start_date = '';
    }
});

watch(dueDateValue, (newValue) => {
    if (newValue) {
        taskForm.due_date = newValue.toString();
    } else {
        taskForm.due_date = '';
    }
});

// Clear the inline error as soon as the title becomes valid
watch(
    () => taskForm.title,
    (title) => {
        if (titleError.value && title.trim()) {
            titleError.value = '';
        }
    },
);

const handleSubmit = () => {
    if (!taskForm.title.trim()) {
        titleError.value = 'Enter a title for the task.';
        return;
    }

    emit('submit', {
        ...taskForm,
        start_date: startDateValue.value ? startDateValue.value.toString() : '',
        due_date: dueDateValue.value ? dueDateValue.value.toString() : '',
    });
};

const createTag = () => {
    if (!newTagName.value.trim() || isCreatingTag.value) return;

    isCreatingTag.value = true;

    // Create the tag in the database with default color
    emit('create-tag', {
        name: newTagName.value.trim(),
        color: '#64748B',
        description: '',
    });

    // Clear the input
    newTagName.value = '';

    // Reset creating state after a delay
    setTimeout(() => {
        isCreatingTag.value = false;
    }, 500);
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="(open) => $emit('update:open', open)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[700px]">
            <DialogHeader>
                <DialogTitle>{{ editingTask ? 'Edit task' : 'New task' }}</DialogTitle>
                <DialogDescription>
                    {{ editingTask ? 'Update the task details below.' : 'Fill in the details to create a new task.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="grid gap-4 py-4">
                <!-- Task Title -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="task-title" class="text-right">Title *</Label>
                    <div class="col-span-3">
                        <Input
                            id="task-title"
                            v-model="taskForm.title"
                            placeholder="Enter task title"
                            required
                            :class="{ 'border-destructive': titleError }"
                        />
                        <p v-if="titleError" class="text-destructive mt-1 text-sm">{{ titleError }}</p>
                    </div>
                </div>

                <!-- Description -->
                <div class="grid grid-cols-4 items-start gap-4">
                    <Label for="task-description" class="pt-2 text-right">Description</Label>
                    <div class="col-span-3">
                        <Textarea id="task-description" v-model="taskForm.description" placeholder="Enter task description" rows="3" />
                    </div>
                </div>

                <!-- Status and Priority -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Status</Label>
                    <div class="col-span-1">
                        <Select v-model="taskForm.status">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="todo">To do</SelectItem>
                                <SelectItem value="in_progress">In progress</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Label class="text-right">Priority</Label>
                    <div class="col-span-1">
                        <Select v-model="taskForm.priority">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Start and Due dates -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label class="text-right">Start date</Label>
                    <div class="col-span-1">
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    class="w-full justify-start text-left font-normal"
                                    :class="!startDateValue && 'text-muted-foreground'"
                                >
                                    <Calendar class="size-4" />
                                    <span class="truncate">
                                        {{ startDateValue ? df.format(startDateValue.toDate(getLocalTimeZone())) : 'Pick a date' }}
                                    </span>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="flex w-auto flex-col gap-y-2 p-2">
                                <CalendarComponent v-model="startDateValue" />
                                <Button
                                    v-if="startDateValue"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground text-xs"
                                    @click="startDateValue = undefined"
                                >
                                    Clear start date
                                </Button>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <Label class="text-right">Due date</Label>
                    <div class="col-span-1">
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    class="w-full justify-start text-left font-normal"
                                    :class="!dueDateValue && 'text-muted-foreground'"
                                >
                                    <Calendar class="size-4" />
                                    <span class="truncate">
                                        {{ dueDateValue ? df.format(dueDateValue.toDate(getLocalTimeZone())) : 'Pick a date' }}
                                    </span>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="flex w-auto flex-col gap-y-2 p-2">
                                <CalendarComponent v-model="dueDateValue" />
                                <Button
                                    v-if="dueDateValue"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground text-xs"
                                    @click="dueDateValue = undefined"
                                >
                                    Clear due date
                                </Button>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                <!-- Tags Section -->
                <div class="grid grid-cols-4 items-start gap-4">
                    <Label class="pt-2 text-right">Tags</Label>
                    <div class="col-span-3 space-y-3">
                        <!-- Selected Tags Display -->
                        <div v-if="taskForm.tag_ids.length > 0" class="flex flex-wrap gap-1">
                            <button
                                v-for="tagId in taskForm.tag_ids"
                                :key="tagId"
                                type="button"
                                :aria-label="`Remove tag ${props.availableTags.find((t) => t.id === tagId)?.name}`"
                                class="hover:bg-destructive/10 focus-visible:ring-ring/50 inline-flex cursor-pointer items-center rounded-md border px-1.5 py-0.5 text-xs font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                :style="`border-color: ${props.availableTags.find((t) => t.id === tagId)?.color}66; color: ${props.availableTags.find((t) => t.id === tagId)?.color}; background-color: ${props.availableTags.find((t) => t.id === tagId)?.color}14`"
                                @click="
                                    () => {
                                        const index = taskForm.tag_ids.indexOf(tagId);
                                        if (index > -1) {
                                            taskForm.tag_ids.splice(index, 1);
                                        }
                                    }
                                "
                            >
                                {{ props.availableTags.find((t) => t.id === tagId)?.name }}
                                <X class="ml-1 size-3" />
                            </button>
                        </div>

                        <!-- Available Tags Selection -->
                        <div v-if="props.availableTags.length > 0">
                            <Label class="text-muted-foreground mb-1 block text-xs font-medium">Select existing tags</Label>
                            <div class="border-border bg-card flex max-h-20 flex-wrap gap-1 overflow-y-auto rounded-md border p-2">
                                <button
                                    v-for="tag in props.availableTags.filter((t) => !taskForm.tag_ids.includes(t.id))"
                                    :key="tag.id"
                                    type="button"
                                    :aria-label="`Add tag ${tag.name}`"
                                    class="border-border text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 inline-flex cursor-pointer items-center rounded-md border px-1.5 py-0.5 text-xs font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                    @click="
                                        () => {
                                            if (!taskForm.tag_ids.includes(tag.id)) {
                                                taskForm.tag_ids.push(tag.id);
                                            }
                                        }
                                    "
                                >
                                    <Plus class="mr-1 size-3" />
                                    {{ tag.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Create New Tag -->
                        <div>
                            <Label class="text-muted-foreground mb-1 block text-xs font-medium">Create new tag</Label>
                            <div class="flex items-center gap-2">
                                <Input v-model="newTagName" placeholder="New tag name" class="h-8 text-sm" @keyup.enter="createTag" />
                                <Button type="button" size="sm" @click="createTag" :disabled="!newTagName.trim() || isCreatingTag" class="h-8 px-2">
                                    {{ isCreatingTag ? 'Creating…' : 'Create' }}
                                </Button>
                            </div>
                            <p class="text-muted-foreground mt-1 text-xs">New tags appear in the selection list above</p>
                        </div>
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="$emit('cancel')" :disabled="isSubmitting">Cancel</Button>
                <Button type="button" @click="handleSubmit" :disabled="isSubmitting">
                    <Loader2 v-if="isSubmitting" class="size-4 animate-spin" />
                    {{ isSubmitting ? 'Saving…' : editingTask ? 'Save changes' : 'Create task' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
