<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Calendar, Clock } from 'lucide-vue-next';

interface Props {
    isOpen: boolean;
    selectedDate?: any;
    editingEvent?: any;
    availableColors: string[];
    isSubmitting: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    create: [eventData: any];
    update: [eventId: string, eventData: any];
}>();

// Form data
const form = reactive({
    title: '',
    description: '',
    start_date: '',
    start_time: '',
    end_date: '',
    end_time: '',
    color: '#3B82F6',
    all_day: false,
});

// Form validation errors
const errors = ref<Record<string, string>>({});

// Dialog title based on editing state
const dialogTitle = computed(() => {
    return props.editingEvent ? 'Edit Event' : 'Create New Event';
});

// Helper functions to avoid timezone issues
const toLocalDateString = (date: Date) => {
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const toLocalTimeString = (date: Date) => {
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
};

// Reset form
const resetForm = () => {
    form.title = '';
    form.description = '';
    form.start_date = '';
    form.start_time = '';
    form.end_date = '';
    form.end_time = '';
    form.color = '#3B82F6';
    form.all_day = false;
    errors.value = {};
};

// Populate form with event data (fixed for timezone issues)
const populateForm = (event: any) => {
    form.title = event.title || '';
    form.description = event.extendedProps?.description || '';
    form.color = event.backgroundColor || '#3B82F6';
    form.all_day = event.allDay || false;

    if (event.start) {
        const startDate = new Date(event.start);
        // Use timezone-safe date extraction
        form.start_date = toLocalDateString(startDate);
        if (!form.all_day) {
            form.start_time = toLocalTimeString(startDate);
        }
    }

    if (event.end) {
        const endDate = new Date(event.end);
        // Use timezone-safe date extraction
        form.end_date = toLocalDateString(endDate);
        if (!form.all_day) {
            form.end_time = toLocalTimeString(endDate);
        }
    }
};

// Populate form with selected date
const populateFromSelectedDate = (selectedDate: any) => {
    if (selectedDate) {
        const startDate = new Date(selectedDate.start);
        form.start_date = toLocalDateString(startDate);
        form.all_day = selectedDate.allDay || false;

        if (!form.all_day && selectedDate.start) {
            form.start_time = toLocalTimeString(startDate);
        }

        if (selectedDate.end && !selectedDate.allDay) {
            const endDate = new Date(selectedDate.end);
            form.end_date = toLocalDateString(endDate);
            form.end_time = toLocalTimeString(endDate);
        } else if (selectedDate.allDay && selectedDate.end) {
            // For all-day selections, end date is exclusive, so subtract a day
            const endDate = new Date(selectedDate.end);
            endDate.setDate(endDate.getDate() - 1);
            form.end_date = toLocalDateString(endDate);
        }
    }
};

// Watch for prop changes
watch(() => props.isOpen, (isOpen) => {
    if (isOpen) {
        resetForm();
        if (props.editingEvent) {
            populateForm(props.editingEvent);
        } else if (props.selectedDate) {
            populateFromSelectedDate(props.selectedDate);
        }
    }
});

// Handle all-day toggle
watch(() => form.all_day, (isAllDay) => {
    if (isAllDay) {
        form.start_time = '';
        form.end_time = '';
    } else if (form.start_date && !form.start_time) {
        form.start_time = '09:00';
        form.end_time = '10:00';
    }
});

// Validate form
const validateForm = (): boolean => {
    errors.value = {};

    if (!form.title.trim()) {
        errors.value.title = 'Event title is required';
    }

    if (!form.start_date) {
        errors.value.start_date = 'Start date is required';
    }

    if (!form.all_day && !form.start_time) {
        errors.value.start_time = 'Start time is required for timed events';
    }

    if (form.end_date && form.start_date && form.end_date < form.start_date) {
        errors.value.end_date = 'End date must be after start date';
    }

    if (!form.all_day && form.end_date && form.end_time && form.start_date === form.end_date && form.end_time <= form.start_time) {
        errors.value.end_time = 'End time must be after start time';
    }

    return Object.keys(errors.value).length === 0;
};

// Build event data for submission (already timezone-safe)
const buildEventData = () => {
    const eventData: any = {
        title: form.title.trim(),
        description: form.description.trim(),
        color: form.color,
        all_day: form.all_day,
    };

    // Build start_date - this creates local datetime strings
    if (form.all_day) {
        eventData.start_date = form.start_date + 'T00:00:00';
    } else {
        eventData.start_date = form.start_date + 'T' + (form.start_time || '00:00') + ':00';
    }

    // Build end_date if provided - this creates local datetime strings
    if (form.end_date) {
        if (form.all_day) {
            eventData.end_date = form.end_date + 'T23:59:59';
        } else {
            eventData.end_date = form.end_date + 'T' + (form.end_time || '23:59') + ':00';
        }
    }

    return eventData;
};

// Handle form submission
const handleSubmit = () => {
    if (!validateForm()) {
        return;
    }

    const eventData = buildEventData();

    if (props.editingEvent) {
        emit('update', props.editingEvent.id, eventData);
    } else {
        emit('create', eventData);
    }
};

// Handle cancel
const handleCancel = () => {
    resetForm();
    emit('close');
};

// Format date for display
const formatDate = (dateString: string): string => {
    if (!dateString) return '';
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="(open) => !open && handleCancel()">
        <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ editingEvent ? 'Update the event details below.' : 'Fill in the details to create a new event.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Event Title -->
                <div class="space-y-2">
                    <Label for="event-title">Event Title *</Label>
                    <Input
                        id="event-title"
                        v-model="form.title"
                        placeholder="Enter event title"
                        :class="{ 'border-red-500': errors.title }"
                    />
                    <p v-if="errors.title" class="text-sm text-red-500">{{ errors.title }}</p>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="event-description">Description</Label>
                    <Textarea
                        id="event-description"
                        v-model="form.description"
                        placeholder="Enter event description (optional)"
                        rows="3"
                    />
                </div>

                <!-- All Day Toggle -->
                <div class="flex items-center space-x-3">
                    <Switch
                        id="all-day"
                        v-model:checked="form.all_day"
                    />
                    <Label for="all-day" class="cursor-pointer">All Day Event</Label>
                </div>

                <!-- Date and Time -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Start Date -->
                    <div class="space-y-2">
                        <Label for="start-date">Start Date *</Label>
                        <div class="relative">
                            <Calendar class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                id="start-date"
                                type="date"
                                v-model="form.start_date"
                                class="pl-10"
                                :class="{ 'border-red-500': errors.start_date }"
                            />
                        </div>
                        <p v-if="errors.start_date" class="text-sm text-red-500">{{ errors.start_date }}</p>
                        <p v-if="form.start_date" class="text-xs text-gray-500">
                            {{ formatDate(form.start_date) }}
                        </p>
                    </div>

                    <!-- Start Time -->
                    <div v-if="!form.all_day" class="space-y-2">
                        <Label for="start-time">Start Time *</Label>
                        <div class="relative">
                            <Clock class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                id="start-time"
                                type="time"
                                v-model="form.start_time"
                                class="pl-10"
                                :class="{ 'border-red-500': errors.start_time }"
                            />
                        </div>
                        <p v-if="errors.start_time" class="text-sm text-red-500">{{ errors.start_time }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- End Date -->
                    <div class="space-y-2">
                        <Label for="end-date">End Date</Label>
                        <div class="relative">
                            <Calendar class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                id="end-date"
                                type="date"
                                v-model="form.end_date"
                                class="pl-10"
                                :class="{ 'border-red-500': errors.end_date }"
                            />
                        </div>
                        <p v-if="errors.end_date" class="text-sm text-red-500">{{ errors.end_date }}</p>
                        <p v-if="form.end_date" class="text-xs text-gray-500">
                            {{ formatDate(form.end_date) }}
                        </p>
                    </div>

                    <!-- End Time -->
                    <div v-if="!form.all_day" class="space-y-2">
                        <Label for="end-time">End Time</Label>
                        <div class="relative">
                            <Clock class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                id="end-time"
                                type="time"
                                v-model="form.end_time"
                                class="pl-10"
                                :class="{ 'border-red-500': errors.end_time }"
                            />
                        </div>
                        <p v-if="errors.end_time" class="text-sm text-red-500">{{ errors.end_time }}</p>
                    </div>
                </div>

                <!-- Color Selection -->
                <div class="space-y-2">
                    <Label>Event Color</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="color in availableColors"
                            :key="color"
                            type="button"
                            @click="form.color = color"
                            class="w-8 h-8 rounded-full border-2 transition-all hover:scale-110"
                            :class="form.color === color ? 'border-gray-900 dark:border-white' : 'border-gray-300 dark:border-gray-600'"
                            :style="{ backgroundColor: color }"
                            :title="color"
                        />
                    </div>
                    <div class="flex items-center space-x-2 mt-2">
                        <Label for="custom-color" class="text-sm">Custom:</Label>
                        <Input
                            id="custom-color"
                            type="color"
                            v-model="form.color"
                            class="w-12 h-8 p-1 border-0"
                        />
                        <span class="text-sm text-gray-500">{{ form.color }}</span>
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="handleCancel"
                    :disabled="isSubmitting"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    @click="handleSubmit"
                    :disabled="isSubmitting"
                >
                    <span v-if="isSubmitting">{{ editingEvent ? 'Updating...' : 'Creating...' }}</span>
                    <span v-else>{{ editingEvent ? 'Update Event' : 'Create Event' }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
