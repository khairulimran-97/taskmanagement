<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Calendar, Clock, Edit, Trash2, X } from 'lucide-vue-next';

interface Props {
    isOpen: boolean;
    event?: any;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    edit: [event: any];
    delete: [eventId: string];
}>();

// Format date for display
const formatDateTime = (date: Date | string, allDay: boolean = false): string => {
    if (!date) return '';

    const dateObj = typeof date === 'string' ? new Date(date) : date;

    if (allDay) {
        return dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    return dateObj.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

// Get event duration text
const getDurationText = computed(() => {
    if (!props.event?.start) return '';

    const start = new Date(props.event.start);
    const end = props.event.end ? new Date(props.event.end) : null;

    if (!end) {
        return 'No end time';
    }

    if (props.event.allDay) {
        const startDate = start.toDateString();
        const endDate = end.toDateString();

        if (startDate === endDate) {
            return 'All day';
        } else {
            const diffTime = end.getTime() - start.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return `${diffDays} day${diffDays > 1 ? 's' : ''}`;
        }
    } else {
        const diffTime = end.getTime() - start.getTime();
        const diffHours = diffTime / (1000 * 60 * 60);
        const diffMinutes = diffTime / (1000 * 60);

        if (diffHours >= 1) {
            const hours = Math.floor(diffHours);
            const minutes = Math.round((diffHours - hours) * 60);
            return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
        } else {
            return `${Math.round(diffMinutes)}m`;
        }
    }
});

// Get relative time text
const getRelativeTime = computed(() => {
    if (!props.event?.start) return '';

    const start = new Date(props.event.start);
    const now = new Date();
    const diffTime = start.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        const pastDays = Math.abs(diffDays);
        if (pastDays === 1) return 'Yesterday';
        if (pastDays < 7) return `${pastDays} days ago`;
        if (pastDays < 30) return `${Math.floor(pastDays / 7)} week${Math.floor(pastDays / 7) > 1 ? 's' : ''} ago`;
        return `${Math.floor(pastDays / 30)} month${Math.floor(pastDays / 30) > 1 ? 's' : ''} ago`;
    } else if (diffDays === 0) {
        return 'Today';
    } else if (diffDays === 1) {
        return 'Tomorrow';
    } else if (diffDays < 7) {
        return `In ${diffDays} days`;
    } else if (diffDays < 30) {
        return `In ${Math.floor(diffDays / 7)} week${Math.floor(diffDays / 7) > 1 ? 's' : ''}`;
    } else {
        return `In ${Math.floor(diffDays / 30)} month${Math.floor(diffDays / 30) > 1 ? 's' : ''}`;
    }
});

// Handle edit
const handleEdit = () => {
    emit('edit', props.event);
};

// Handle delete
const handleDelete = () => {
    if (props.event?.id) {
        emit('delete', props.event.id);
    }
};

// Handle close
const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="(open) => !open && handleClose()">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <DialogTitle class="flex items-center space-x-2">
                        <div
                            class="w-4 h-4 rounded-full border border-white shadow-sm"
                            :style="{ backgroundColor: event?.backgroundColor || '#3B82F6' }"
                        />
                        <span>{{ event?.title || 'Event Details' }}</span>
                    </DialogTitle>
                    <Button variant="ghost" size="icon" @click="handleClose" class="h-6 w-6">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
                <DialogDescription>
                    {{ event?.extendedProps?.description || 'View and manage this calendar event.' }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="event" class="space-y-6">
                <!-- Event Times -->
                <div class="space-y-3">
                    <!-- Start Time -->
                    <div class="flex items-start space-x-3">
                        <Calendar class="h-5 w-5 text-gray-400 mt-0.5" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ formatDateTime(event.start, event.allDay) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Start {{ getRelativeTime }}
                            </p>
                        </div>
                    </div>

                    <!-- End Time -->
                    <div v-if="event.end" class="flex items-start space-x-3">
                        <Clock class="h-5 w-5 text-gray-400 mt-0.5" />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                {{ formatDateTime(event.end, event.allDay) }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Duration: {{ getDurationText }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Event Properties -->
                <div class="flex flex-wrap gap-2">
                    <Badge v-if="event.allDay" variant="secondary" class="flex items-center space-x-1">
                        <Clock class="h-3 w-3" />
                        <span>All Day</span>
                    </Badge>

                    <Badge
                        variant="outline"
                        class="flex items-center space-x-1"
                        :style="{ borderColor: event.backgroundColor, color: event.backgroundColor }"
                    >
                        <div
                            class="w-2 h-2 rounded-full"
                            :style="{ backgroundColor: event.backgroundColor }"
                        />
                        <span>{{ event.backgroundColor }}</span>
                    </Badge>
                </div>

                <!-- Description -->
                <div v-if="event.extendedProps?.description" class="space-y-2">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">Description</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">
                        {{ event.extendedProps.description }}
                    </p>
                </div>
            </div>

            <DialogFooter class="flex justify-between">
                <div class="flex space-x-2">
                    <Button variant="outline" @click="handleEdit" class="flex items-center space-x-2">
                        <Edit class="h-4 w-4" />
                        <span>Edit</span>
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="outline" class="flex items-center space-x-2 text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-950">
                                <Trash2 class="h-4 w-4" />
                                <span>Delete</span>
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete Event</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Are you sure you want to delete "{{ event?.title }}"? This action cannot be undone.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    @click="handleDelete"
                                    class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                                >
                                    Delete Event
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>

                <Button @click="handleClose">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
