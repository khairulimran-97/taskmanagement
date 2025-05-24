<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { Plus, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { DateFormatter, type DateValue, parseDate } from '@internationalized/date';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    projectId: {
        type: Number,
        required: true
    },
    parentTaskId: {
        type: Number,
        required: true
    },
    editingSubtask: {
        type: Object,
        default: null
    },
    availableTags: {
        type: Array,
        default: () => []
    },
    isSubmitting: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['submit', 'cancel', 'create-tag']);

const df = new DateFormatter('en-US', { dateStyle: 'long' });
const subtaskStartDateValue = ref<DateValue>();
const subtaskDueDateValue = ref<DateValue>();

// New tag state
const newTagName = ref('');
const isCreatingTag = ref(false);

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

    // Set date values if available
    if (task.start_date) {
        try {
            subtaskStartDateValue.value = parseDate(task.start_date.split('T')[0]);
        } catch (e) {
            subtaskStartDateValue.value = undefined;
        }
    } else {
        subtaskStartDateValue.value = undefined;
    }

    if (task.due_date) {
        try {
            subtaskDueDateValue.value = parseDate(task.due_date.split('T')[0]);
        } catch (e) {
            subtaskDueDateValue.value = undefined;
        }
    } else {
        subtaskDueDateValue.value = undefined;
    }
};

const handleSubmit = () => {
    emit('submit', {
        ...subtaskForm,
        start_date: subtaskStartDateValue.value ? subtaskStartDateValue.value.toString() : '',
        due_date: subtaskDueDateValue.value ? subtaskDueDateValue.value.toString() : ''
    });
};

const createTag = () => {
    if (!newTagName.value.trim() || isCreatingTag.value) return;

    isCreatingTag.value = true;

    // Create the tag in the database with default color
    emit('create-tag', {
        name: newTagName.value.trim(),
        color: '#6B7280',
        description: ''
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
watch(() => props.parentTaskId, (newVal) => {
    subtaskForm.parent_task_id = newVal;
});

// Watch for editing subtask changes
watch(() => props.editingSubtask, (task) => {
    if (task) {
        populateForm(task);
    } else {
        resetForm();
    }
}, { immediate: true });

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
</script>

<template>
    <div
        v-if="isOpen"
        class="mt-5 mb-4 rounded-lg border bg-gray-50 p-3 dark:bg-gray-800 dark:border-gray-700"
    >
        <h5 class="mb-3 text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ editingSubtask ? 'Edit Subtask' : 'Create Subtask' }}
        </h5>

        <div class="space-y-3">
            <div>
                <Label for="subtask-title" class="text-xs text-gray-700 dark:text-gray-300">Title *</Label>
                <Input
                    id="subtask-title"
                    v-model="subtaskForm.title"
                    placeholder="Enter subtask title"
                    required
                    class="h-8 text-xs bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600"
                />
            </div>

            <div>
                <Label for="subtask-description" class="text-xs text-gray-700 dark:text-gray-300">Description</Label>
                <Textarea
                    id="subtask-description"
                    v-model="subtaskForm.description"
                    placeholder="Enter subtask description"
                    rows="2"
                    class="text-xs bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="w-full">
                    <Label class="text-xs text-gray-700 dark:text-gray-300">Status</Label>
                    <Select v-model="subtaskForm.status">
                        <SelectTrigger class="h-8 text-xs bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <SelectItem value="todo">To Do</SelectItem>
                            <SelectItem value="in_progress">In Progress</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full">
                    <Label class="text-xs text-gray-700 dark:text-gray-300">Priority</Label>
                    <Select v-model="subtaskForm.priority">
                        <SelectTrigger class="h-8 text-xs bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent class="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <SelectItem value="low">Low</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="urgent">Urgent</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Tags Section -->
            <div class="space-y-2">
                <Label class="text-xs text-gray-700 dark:text-gray-300">Tags</Label>

                <!-- Selected Tags Display -->
                <div
                    v-if="subtaskForm.tag_ids.length > 0"
                    class="flex flex-wrap gap-1 rounded-md border bg-white p-2 dark:bg-gray-700 dark:border-gray-600"
                >
                    <Badge
                        v-for="tagId in subtaskForm.tag_ids"
                        :key="tagId"
                        variant="outline"
                        class="cursor-pointer px-1.5 py-0.5 text-xs hover:bg-red-50 dark:hover:bg-red-900 flex items-center"
                        :style="`border-color: ${props.availableTags.find((t) => t.id === tagId)?.color}; color: ${props.availableTags.find((t) => t.id === tagId)?.color}`"
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
                        <X class="ml-1 h-3 w-3" />
                    </Badge>
                </div>

                <!-- Available Tags Selection -->
                <div v-if="props.availableTags.length > 0">
                    <div
                        class="flex max-h-20 flex-wrap gap-1 overflow-y-auto rounded-md border bg-white p-2 dark:bg-gray-700 dark:border-gray-600"
                    >
                        <Badge
                            v-for="tag in props.availableTags.filter((t) => !subtaskForm.tag_ids.includes(t.id))"
                            :key="tag.id"
                            variant="outline"
                            class="cursor-pointer px-1.5 py-0.5 text-xs transition-colors hover:bg-gray-50 dark:hover:bg-gray-600"
                            @click="
                                () => {
                                  if (!subtaskForm.tag_ids.includes(tag.id)) {
                                    subtaskForm.tag_ids.push(tag.id);
                                  }
                                }
                              "
                        >
                            <Plus class="mr-1 h-3 w-3" />
                            {{ tag.name }}
                        </Badge>
                    </div>
                </div>

                <!-- Create New Tag -->
                <div class="mt-3 space-y-1">
                    <Label class="text-xs font-medium text-gray-600 dark:text-gray-400">Create new tag</Label>
                    <div class="flex items-center space-x-2">
                        <Input
                            v-model="newTagName"
                            placeholder="New tag name"
                            class="h-7 flex-1 text-xs bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600"
                            @keyup.enter="createTag"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="createTag"
                            :disabled="!newTagName.trim() || isCreatingTag"
                            class="h-7 px-2 text-xs whitespace-nowrap dark:text-gray-100 dark:border-gray-600 dark:hover:bg-gray-600"
                        >
                            {{ isCreatingTag ? 'Creating...' : 'Create' }}
                        </Button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        New tag will appear in the selection list above
                    </p>
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <Button
                    @click="handleSubmit"
                    :disabled="isSubmitting"
                    size="sm"
                    class="h-7 px-3 text-xs dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                >
                    {{ isSubmitting ? 'Saving...' : editingSubtask ? 'Update' : 'Create' }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="$emit('cancel')"
                    class="h-7 px-3 text-xs dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    Cancel
                </Button>
            </div>
        </div>
    </div>
</template>
