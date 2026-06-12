<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Calendar, Clock, Edit, Trash2 } from 'lucide-vue-next';
import { categoryForColor } from '@/lib/eventCategories';

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

// Helper function to parse date without timezone conversion
const parseLocalDate = (date: Date | string): Date => {
    if (!date) return new Date();

    if (typeof date === 'string') {
        // If it's a string in format YYYY-MM-DDTHH:mm:ss, treat as local time
        if (date.includes('T') && !date.includes('Z') && !date.includes('+')) {
            const parts = date.split('T');
            const dateParts = parts[0].split('-');
            const timeParts = parts[1] ? parts[1].split(':') : ['0', '0', '0'];

            return new Date(
                parseInt(dateParts[0]), // year
                parseInt(dateParts[1]) - 1, // month (0-indexed)
                parseInt(dateParts[2]), // day
                parseInt(timeParts[0]) || 0, // hour
                parseInt(timeParts[1]) || 0, // minute
                parseInt(timeParts[2]) || 0  // second
            );
        }
        // For other string formats, fall back to regular parsing
        return new Date(date);
    }

    return date;
};

// Format date for display (timezone-safe)
const formatDateTime = (date: Date | string, allDay: boolean = false): string => {
    if (!date) return '';

    const dateObj = parseLocalDate(date);

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

// Get event duration text (timezone-safe)
const getDurationText = computed(() => {
    if (!props.event?.start) return '';

    const start = parseLocalDate(props.event.start);
    const end = props.event.end ? parseLocalDate(props.event.end) : null;

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

// Get relative time text (timezone-safe)
const getRelativeTime = computed(() => {
    if (!props.event?.start) return '';

    const start = parseLocalDate(props.event.start);
    const now = new Date();

    // Compare dates at the day level to avoid time-of-day issues
    const startDate = new Date(start.getFullYear(), start.getMonth(), start.getDate());
    const todayDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    const diffTime = startDate.getTime() - todayDate.getTime();
    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

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
    <Sheet :open="isOpen" @update:open="(open) => !open && handleClose()">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-b border-border px-5 py-4 text-left">
                <SheetTitle class="flex items-center gap-2.5">
                    <span
                        class="h-4 w-4 shrink-0 rounded-full ring-1 ring-border"
                        :style="{ backgroundColor: event?.backgroundColor || 'var(--primary)' }"
                    />
                    <span class="truncate font-display text-xl tracking-tight">{{ event?.title || 'Event Details' }}</span>
                </SheetTitle>
                <SheetDescription>
                    View and manage this calendar event.
                </SheetDescription>
            </SheetHeader>

            <div v-if="event" class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                <!-- Event Times -->
                <div class="space-y-3">
                    <!-- Start Time -->
                    <div class="flex items-start space-x-3">
                        <Calendar class="h-5 w-5 text-muted-foreground mt-0.5" />
                        <div>
                            <p class="font-medium text-foreground">
                                {{ formatDateTime(event.start, event.allDay) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Start {{ getRelativeTime }}
                            </p>
                        </div>
                    </div>

                    <!-- End Time -->
                    <div v-if="event.end" class="flex items-start space-x-3">
                        <Clock class="h-5 w-5 text-muted-foreground mt-0.5" />
                        <div>
                            <p class="font-medium text-foreground">
                                {{ formatDateTime(event.end, event.allDay) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
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
                        class="flex items-center gap-1.5"
                        :style="{ borderColor: event.backgroundColor, color: event.backgroundColor }"
                    >
                        <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: event.backgroundColor }" />
                        <span>{{ categoryForColor(event.backgroundColor).label }}</span>
                    </Badge>
                </div>

                <!-- Description -->
                <div v-if="event.extendedProps?.description" class="space-y-2">
                    <h4 class="font-medium text-foreground">Description</h4>
                    <p class="text-sm text-muted-foreground whitespace-pre-wrap">
                        {{ event.extendedProps.description }}
                    </p>
                </div>
            </div>

            <SheetFooter class="flex-row items-center justify-between gap-2 border-t border-border px-5 py-4">
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="handleEdit" class="gap-1.5">
                        <Edit class="h-4 w-4" />
                        <span>Edit</span>
                    </Button>

                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="sm" class="gap-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive">
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
                                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90 focus:ring-destructive"
                                >
                                    Delete Event
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>

                <Button size="sm" @click="handleClose">
                    Close
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
