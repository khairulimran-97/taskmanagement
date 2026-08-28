<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DateFormatter, type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';
import { Calendar as CalendarIcon, Loader2, Plus, X } from 'lucide-vue-next';
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
    parentTaskId: {
        type: Number,
        required: true,
    },
    editingSubtask: {
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

const emit = defineEmits(['submit', 'cancel', 'create-tag']);

const df = new DateFormatter('en-US', { dateStyle: 'medium' });
const subtaskStartDateValue = ref<DateValue>();
const subtaskDueDateValue = ref<DateValue>();

// New tag state
const newTagName = ref('');
const isCreatingTag = ref(false);

// Client-side guard: the submit button bypasses native form validation
const titleError = ref('');

// Subtask form data
const subtaskForm = reactive({
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    due_date: '',
    start_date: '',
    project_id: props.projectId,
    assigned_to: null as number | null,
    parent_task_id: props.parentTaskId,
    tag_ids: [] as number[],
    new_tags: [] as string[],
});

// Define functions first before using in watchers
const resetForm = () => {
    subtaskForm.title = '';
    subtaskForm.description = '';
    subtaskForm.status = 'todo';
    subtaskForm.priority = 'medium';
    subtaskForm.due_date = '';
    subtaskForm.start_date = '';
    subtaskForm.assigned_to = null;
    subtaskForm.parent_task_id = props.parentTaskId;
    subtaskForm.tag_ids = [];
    subtaskForm.new_tags = [];
    subtaskStartDateValue.value = undefined;
    subtaskDueDateValue.value = undefined;
    newTagName.value = '';
    titleError.value = '';
};

const populateForm = (task: any) => {
    subtaskForm.title = task.title || '';
    subtaskForm.description = task.description || '';
    subtaskForm.status = task.status || 'todo';
    subtaskForm.priority = task.priority || 'medium';
    subtaskForm.due_date = task.due_date || '';
    subtaskForm.start_date = task.start_date || '';
    subtaskForm.assigned_to = task.assigned_to || null;
    subtaskForm.parent_task_id = task.parent_task_id || props.parentTaskId;
    subtaskForm.tag_ids = task.tags?.map((tag: any) => tag.id) || [];
    titleError.value = '';

    // Set date values if available
    if (task.start_date) {
        try {
            subtaskStartDateValue.value = parseDate(task.start_date.split('T')[0]);
        } catch {
            subtaskStartDateValue.value = undefined;
        }
    } else {
        subtaskStartDateValue.value = undefined;
    }

    if (task.due_date) {
        try {
            subtaskDueDateValue.value = parseDate(task.due_date.split('T')[0]);
        } catch {
            subtaskDueDateValue.value = undefined;
        }
    } else {
        subtaskDueDateValue.value = undefined;
    }
};

const handleSubmit = () => {
    if (!subtaskForm.title.trim()) {
        titleError.value = 'Enter a title for the subtask.';
        return;
    }

    emit('submit', {
        ...subtaskForm,
        start_date: subtaskStartDateValue.value ? subtaskStartDateValue.value.toString() : '',
        due_date: subtaskDueDateValue.value ? subtaskDueDateValue.value.toString() : '',
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

// Now set up watchers after functions are defined
// Watch for parent task id changes
watch(
    () => props.parentTaskId,
    (newVal) => {
        subtaskForm.parent_task_id = newVal;
    },
);

// Watch for editing subtask changes
watch(
    () => props.editingSubtask,
    (task) => {
        if (task) {
            populateForm(task);
        } else {
            resetForm();
        }
    },
    { immediate: true },
);

// Watch for date changes
watch(subtaskStartDateValue, (newValue) => {
    if (newValue) {
        subtaskForm.start_date = newValue.toString();
    } else {
        subtaskForm.start_date = '';
    }
});

watch(subtaskDueDateValue, (newValue) => {
    if (newValue) {
        subtaskForm.due_date = newValue.toString();
    } else {
        subtaskForm.due_date = '';
    }
});

// Clear the inline error as soon as the title becomes valid
watch(
    () => subtaskForm.title,
    (title) => {
        if (titleError.value && title.trim()) {
            titleError.value = '';
        }
    },
);
</script>

<template>
    <div v-if="isOpen" class="border-border bg-muted/30 mt-5 mb-4 rounded-lg border p-3">
        <h5 class="text-foreground mb-3 text-sm font-semibold">
            {{ editingSubtask ? 'Edit subtask' : 'New subtask' }}
        </h5>

        <div class="space-y-3">
            <div>
                <Label for="subtask-title" class="text-xs">Title *</Label>
                <Input
                    id="subtask-title"
                    v-model="subtaskForm.title"
                    placeholder="Enter subtask title"
                    required
                    class="h-8 text-sm"
                    :class="{ 'border-destructive': titleError }"
                />
                <p v-if="titleError" class="text-destructive mt-1 text-xs">{{ titleError }}</p>
            </div>

            <div>
                <Label for="subtask-description" class="text-xs">Description</Label>
                <Textarea
                    id="subtask-description"
                    v-model="subtaskForm.description"
                    placeholder="Enter subtask description"
                    rows="2"
                    class="text-sm"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="w-full">
                    <Label class="text-xs">Status</Label>
                    <Select v-model="subtaskForm.status">
                        <SelectTrigger class="h-8 text-xs">
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

                <div class="w-full">
                    <Label class="text-xs">Priority</Label>
                    <Select v-model="subtaskForm.priority">
                        <SelectTrigger class="h-8 text-xs">
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

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-3">
                <div class="w-full">
                    <Label class="text-xs">Start date</Label>
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 w-full justify-start text-left text-xs font-normal"
                                :class="!subtaskStartDateValue && 'text-muted-foreground'"
                            >
                                <CalendarIcon class="size-3.5" />
                                <span class="truncate">
                                    {{ subtaskStartDateValue ? df.format(subtaskStartDateValue.toDate(getLocalTimeZone())) : 'Pick a date' }}
                                </span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="flex w-auto flex-col gap-y-2 p-2">
                            <CalendarComponent v-model="subtaskStartDateValue" />
                            <Button
                                v-if="subtaskStartDateValue"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground text-xs"
                                @click="subtaskStartDateValue = undefined"
                            >
                                Clear start date
                            </Button>
                        </PopoverContent>
                    </Popover>
                </div>

                <div class="w-full">
                    <Label class="text-xs">Due date</Label>
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-8 w-full justify-start text-left text-xs font-normal"
                                :class="!subtaskDueDateValue && 'text-muted-foreground'"
                            >
                                <CalendarIcon class="size-3.5" />
                                <span class="truncate">
                                    {{ subtaskDueDateValue ? df.format(subtaskDueDateValue.toDate(getLocalTimeZone())) : 'Pick a date' }}
                                </span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="flex w-auto flex-col gap-y-2 p-2">
                            <CalendarComponent v-model="subtaskDueDateValue" />
                            <Button
                                v-if="subtaskDueDateValue"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground text-xs"
                                @click="subtaskDueDateValue = undefined"
                            >
                                Clear due date
                            </Button>
                        </PopoverContent>
                    </Popover>
                </div>
            </div>

            <!-- Tags Section -->
            <div class="space-y-2">
                <Label class="text-xs">Tags</Label>

                <!-- Selected Tags Display -->
                <div v-if="subtaskForm.tag_ids.length > 0" class="border-border bg-card flex flex-wrap gap-1 rounded-md border p-2">
                    <button
                        v-for="tagId in subtaskForm.tag_ids"
                        :key="tagId"
                        type="button"
                        :aria-label="`Remove tag ${props.availableTags.find((t) => t.id === tagId)?.name}`"
                        class="hover:bg-destructive/10 focus-visible:ring-ring/50 inline-flex cursor-pointer items-center rounded-md border px-1.5 py-0.5 text-xs font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        :style="`border-color: ${props.availableTags.find((t) => t.id === tagId)?.color}66; color: ${props.availableTags.find((t) => t.id === tagId)?.color}; background-color: ${props.availableTags.find((t) => t.id === tagId)?.color}14`"
                        @click="
                            () => {
                                const index = subtaskForm.tag_ids.indexOf(tagId);
                                if (index > -1) {
                                    subtaskForm.tag_ids.splice(index, 1);
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
                    <div class="border-border bg-card flex max-h-20 flex-wrap gap-1 overflow-y-auto rounded-md border p-2">
                        <button
                            v-for="tag in props.availableTags.filter((t) => !subtaskForm.tag_ids.includes(t.id))"
                            :key="tag.id"
                            type="button"
                            :aria-label="`Add tag ${tag.name}`"
                            class="border-border text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 inline-flex cursor-pointer items-center rounded-md border px-1.5 py-0.5 text-xs font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                            @click="
                                () => {
                                    if (!subtaskForm.tag_ids.includes(tag.id)) {
                                        subtaskForm.tag_ids.push(tag.id);
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
                <div class="mt-3 space-y-1">
                    <Label class="text-muted-foreground text-xs font-medium">Create new tag</Label>
                    <div class="flex items-center gap-2">
                        <Input v-model="newTagName" placeholder="New tag name" class="h-7 flex-1 text-xs" @keyup.enter="createTag" />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="createTag"
                            :disabled="!newTagName.trim() || isCreatingTag"
                            class="h-7 px-2 text-xs whitespace-nowrap"
                        >
                            {{ isCreatingTag ? 'Creating…' : 'Create' }}
                        </Button>
                    </div>
                    <p class="text-muted-foreground text-xs">New tags appear in the selection list above</p>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <Button @click="handleSubmit" :disabled="isSubmitting" size="sm" class="h-7 px-3 text-xs">
                    <Loader2 v-if="isSubmitting" class="size-3 animate-spin" />
                    {{ isSubmitting ? 'Saving…' : editingSubtask ? 'Save changes' : 'Add subtask' }}
                </Button>
                <Button variant="outline" size="sm" @click="$emit('cancel')" :disabled="isSubmitting" class="h-7 px-3 text-xs"> Cancel </Button>
            </div>
        </div>
    </div>
</template>
