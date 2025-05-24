<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { Calendar, Plus, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DateFormatter, type DateValue, getLocalTimeZone, parseDate } from '@internationalized/date';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    projectId: {
        type: Number,
        required: true
    },
    editingTask: {
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

const emit = defineEmits(['update:open', 'submit', 'cancel', 'create-tag']);

const df = new DateFormatter('en-US', { dateStyle: 'long' });
const startDateValue = ref<DateValue>();
const dueDateValue = ref<DateValue>();

// New tag state
const newTagName = ref('');
const isCreatingTag = ref(false);

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
    new_tags: []
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
    taskForm.tag_ids = task.tags?.map(tag => tag.id) || [];

    // Set date values if available
    if (task.start_date) {
        try {
            startDateValue.value = parseDate(task.start_date.split('T')[0]);
        } catch (e) {
            startDateValue.value = undefined;
        }
    } else {
        startDateValue.value = undefined;
    }

    if (task.due_date) {
        try {
            dueDateValue.value = parseDate(task.due_date.split('T')[0]);
        } catch (e) {
            dueDateValue.value = undefined;
        }
    } else {
        dueDateValue.value = undefined;
    }
};

watch(() => props.editingTask, (task) => {
    if (task) {
        populateForm(task);
    } else {
        resetForm();
    }
}, { immediate: true });


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

const handleSubmit = () => {
    emit('submit', {
        ...taskForm,
        start_date: startDateValue.value ? startDateValue.value.toString() : '',
        due_date: dueDateValue.value ? dueDateValue.value.toString() : ''
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

</script>

<template>
    <Dialog
        :open="isOpen"
        @update:open="(open) => $emit('update:open', open)"
    >
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[700px]">
            <DialogHeader>
                <DialogTitle>{{ editingTask ? 'Edit Task' : 'Add New Task' }}</DialogTitle>
                <DialogDescription>
                    {{ editingTask ? 'Update the task details below.' : 'Fill in the details to create a new task.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="grid gap-4 py-4">
                <!-- Task Title -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="task-title" class="text-right">Title *</Label>
                    <div class="col-span-3">
                        <Input id="task-title" v-model="taskForm.title" placeholder="Enter task title" required />
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
                                <SelectItem value="todo">To Do</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
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

                <!-- Tags Section -->
                <div class="grid grid-cols-4 items-start gap-4">
                    <Label class="pt-2 text-right">Tags</Label>
                    <div class="col-span-3 space-y-3">
                        <!-- Selected Tags Display -->
                        <div v-if="taskForm.tag_ids.length > 0" class="flex flex-wrap gap-1 mb-2">
                            <Badge
                                v-for="tagId in taskForm.tag_ids"
                                :key="tagId"
                                variant="outline"
                                class="cursor-pointer px-1.5 py-0.5 text-xs hover:bg-red-50 flex items-center"
                                :style="`border-color: ${props.availableTags.find(t => t.id === tagId)?.color}; color: ${props.availableTags.find(t => t.id === tagId)?.color}`"
                                @click="() => {
                                    const index = taskForm.tag_ids.indexOf(tagId);
                                    if (index > -1) {
                                        taskForm.tag_ids.splice(index, 1);
                                    }
                                }"
                            >
                                {{ props.availableTags.find(t => t.id === tagId)?.name }}
                                <X class="ml-1 h-3 w-3" />
                            </Badge>
                        </div>

                        <!-- Available Tags Selection -->
                        <div v-if="props.availableTags.length > 0" class="mb-3">
                            <Label class="text-xs font-medium text-gray-600 mb-1 block">Select existing tags</Label>
                            <div class="flex max-h-20 flex-wrap gap-1 overflow-y-auto rounded-md border bg-white p-2">
                                <Badge
                                    v-for="tag in props.availableTags.filter(t => !taskForm.tag_ids.includes(t.id))"
                                    :key="tag.id"
                                    variant="outline"
                                    class="cursor-pointer px-1.5 py-0.5 text-xs transition-colors hover:bg-gray-50"
                                    :style="`border-color: ${tag.color}; color: ${tag.color}`"
                                    @click="() => {
                                        if (!taskForm.tag_ids.includes(tag.id)) {
                                            taskForm.tag_ids.push(tag.id);
                                        }
                                    }"
                                >
                                    <Plus class="mr-1 h-3 w-3" />
                                    {{ tag.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Create New Tag -->
                        <div>
                            <Label class="text-xs font-medium text-gray-600 mb-1 block">Create new tag</Label>
                            <div class="flex items-center space-x-2">
                                <Input
                                    v-model="newTagName"
                                    placeholder="New tag name"
                                    class="h-8 text-sm"
                                    @keyup.enter="createTag"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    @click="createTag"
                                    :disabled="!newTagName.trim() || isCreatingTag"
                                    class="h-8 px-2"
                                >
                                    {{ isCreatingTag ? 'Creating...' : 'Create' }}
                                </Button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                New tag will appear in the selection list above
                            </p>
                        </div>
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="$emit('cancel')" :disabled="isSubmitting"> Cancel </Button>
                <Button type="button" @click="handleSubmit" :disabled="isSubmitting">
                    {{ isSubmitting ? 'Saving...' : editingTask ? 'Update Task' : 'Create Task' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
