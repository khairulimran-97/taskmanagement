<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CalendarEvent, FullCalendarEvent } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Calendar as CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-vue-next';

// FullCalendar imports
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';

import CalendarEventDialog from '@/components/calendar/CalendarEventDialog.vue';
import CalendarEventDetailDialog from '@/components/calendar/CalendarEventDetailDialog.vue';

interface Props {
    availableColors: string[];
    events?: CalendarEvent[];
}

const props = defineProps<Props>();

// Define breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Calendar', href: route('calendar.index') },
]);

// Calendar ref and state
const calendarRef = ref();
const currentView = ref('dayGridMonth');
const currentDate = ref(new Date());

// Modal states
const isEventDialogOpen = ref(false);
const isEventDetailDialogOpen = ref(false);
const editingEvent = ref<FullCalendarEvent | null>(null);
const selectedEvent = ref<FullCalendarEvent | null>(null);
const selectedDate = ref<any>(null);
const isSubmitting = ref(false);

// Convert Laravel events to FullCalendar format
const calendarEvents = computed(() => {
    return (props.events || []).map((event: CalendarEvent): FullCalendarEvent => ({
        id: event.id,
        title: event.title,
        start: event.start_date,
        end: event.end_date || undefined,
        allDay: event.all_day,
        backgroundColor: event.color,
        borderColor: event.color,
        textColor: '#ffffff',
        extendedProps: {
            description: event.description,
            user_id: event.user_id,
        }
    }));
});

// Calendar options
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },
    initialView: 'dayGridMonth',
    height: 'auto',
    selectable: true,
    selectMirror: true,
    dayMaxEvents: true,
    weekends: true,
    editable: true,
    eventResizableFromStart: true,
    eventDurationEditable: true,
    events: calendarEvents.value,

    // Event handlers
    select: handleDateSelect,
    eventClick: handleEventClick,
    eventDrop: handleEventDrop,
    eventResize: handleEventResize,

    // Event styling
    eventDisplay: 'block',
    eventTextColor: '#ffffff',

    // View-specific options
    views: {
        dayGridMonth: {
            dayMaxEventRows: 3,
        },
        timeGridWeek: {
            allDaySlot: true,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
        },
        timeGridDay: {
            allDaySlot: true,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
        }
    }
}));

// Handle date selection (clicking on calendar)
function handleDateSelect(selectInfo: any) {
    selectedDate.value = selectInfo;
    editingEvent.value = null;
    isEventDialogOpen.value = true;
}

// Handle event click
function handleEventClick(clickInfo: any) {
    selectedEvent.value = {
        id: clickInfo.event.id,
        title: clickInfo.event.title,
        start: clickInfo.event.start ? clickInfo.event.start.toISOString() : '',
        end: clickInfo.event.end ? clickInfo.event.end.toISOString() : undefined,
        allDay: clickInfo.event.allDay,
        backgroundColor: clickInfo.event.backgroundColor,
        borderColor: clickInfo.event.borderColor,
        textColor: clickInfo.event.textColor,
        extendedProps: clickInfo.event.extendedProps,
    };
    isEventDetailDialogOpen.value = true;
}

// Handle event drag and drop
function handleEventDrop(dropInfo: any) {
    const event = dropInfo.event;

    const updateData = {
        start_date: event.start.toISOString(),
        end_date: event.end ? event.end.toISOString() : null,
        all_day: event.allDay,
    };

    router.patch(route('calendar.update-dates', event.id), updateData, {
        preserveScroll: true,
        onError: () => {
            dropInfo.revert();
        }
    });
}

// Handle event resize
function handleEventResize(resizeInfo: any) {
    const event = resizeInfo.event;

    const updateData = {
        start_date: event.start.toISOString(),
        end_date: event.end ? event.end.toISOString() : null,
        all_day: event.allDay,
    };

    router.patch(route('calendar.update-dates', event.id), updateData, {
        preserveScroll: true,
        onError: () => {
            resizeInfo.revert();
        }
    });
}

// Handle view/date changes
const handleViewChange = (viewInfo: any) => {
    currentView.value = viewInfo.view.type;
    currentDate.value = viewInfo.view.currentStart;
};

// Create new event
const createEvent = (eventData: any) => {
    isSubmitting.value = true;

    router.post(route('calendar.store'), eventData, {
        preserveScroll: true,
        onSuccess: () => {
            isEventDialogOpen.value = false;
            selectedDate.value = null;
            resetForm();
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

// Update event
const updateEvent = (eventId: string, eventData: any) => {
    isSubmitting.value = true;

    router.put(route('calendar.update', eventId), eventData, {
        preserveScroll: true,
        onSuccess: () => {
            isEventDialogOpen.value = false;
            isEventDetailDialogOpen.value = false;
            editingEvent.value = null;
            resetForm();
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

// Delete event
const deleteEvent = (eventId: string) => {
    router.delete(route('calendar.destroy', eventId), {
        preserveScroll: true,
        onSuccess: () => {
            isEventDetailDialogOpen.value = false;
            selectedEvent.value = null;
        },
        onError: (errors) => {
            console.error('Delete error:', errors);
        }
    });
};

// Handle edit event
const handleEditEvent = (event: FullCalendarEvent) => {
    editingEvent.value = event;
    isEventDetailDialogOpen.value = false;
    isEventDialogOpen.value = true;
};

// Reset form state
const resetForm = () => {
    editingEvent.value = null;
    selectedDate.value = null;
    selectedEvent.value = null;
};

// Calendar navigation
const navigateCalendar = (action: string) => {
    if (calendarRef.value) {
        const calendarApi = calendarRef.value.getApi();

        switch (action) {
            case 'prev':
                calendarApi.prev();
                break;
            case 'next':
                calendarApi.next();
                break;
            case 'today':
                calendarApi.today();
                break;
        }

        // Update current date for display
        currentDate.value = calendarApi.view.currentStart;
    }
};

// Change calendar view
const changeView = (viewName: string) => {
    if (calendarRef.value) {
        const calendarApi = calendarRef.value.getApi();
        calendarApi.changeView(viewName);
        currentView.value = viewName;
        currentDate.value = calendarApi.view.currentStart;
    }
};

// Format current date for display
const formatCurrentDate = computed(() => {
    if (currentView.value === 'dayGridMonth') {
        return currentDate.value.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric'
        });
    } else if (currentView.value.includes('Week')) {
        const startOfWeek = new Date(currentDate.value);
        const endOfWeek = new Date(currentDate.value);
        endOfWeek.setDate(startOfWeek.getDate() + 6);

        return `${startOfWeek.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        })} - ${endOfWeek.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        })}`;
    } else {
        return currentDate.value.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    }
});

// Open new event dialog
const openNewEventDialog = () => {
    resetForm();
    isEventDialogOpen.value = true;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Calendar" />

        <div class="container mx-auto px-4 py-6 max-w-7xl">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <CalendarIcon class="h-8 w-8 text-blue-600" />
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Calendar</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Manage your personal events and appointments</p>
                        </div>
                    </div>
                    <Button @click="openNewEventDialog" class="flex items-center space-x-2">
                        <Plus class="h-4 w-4" />
                        <span>New Event</span>
                    </Button>
                </div>
            </div>

            <!-- Calendar -->
            <Card>
                <CardContent class="p-6">
                    <FullCalendar
                        title=""
                        ref="calendarRef"
                        :options="calendarOptions"
                        class="calendar-container"
                    />
                </CardContent>
            </Card>
        </div>

        <!-- Event Creation/Edit Dialog -->
        <CalendarEventDialog
            :is-open="isEventDialogOpen"
            :selected-date="selectedDate"
            :editing-event="editingEvent"
            :available-colors="availableColors"
            :is-submitting="isSubmitting"
            @close="isEventDialogOpen = false; resetForm()"
            @create="createEvent"
            @update="updateEvent"
        />

        <!-- Event Detail Dialog -->
        <CalendarEventDetailDialog
            :is-open="isEventDetailDialogOpen"
            :event="selectedEvent"
            @close="isEventDetailDialogOpen = false; selectedEvent = null"
            @edit="handleEditEvent"
            @delete="deleteEvent"
        />
    </AppLayout>
</template>

<style>
/* Calendar styling */
.calendar-container {
    --fc-border-color: #e5e7eb;
    --fc-button-text-color: #374151;
    --fc-button-bg-color: #f9fafb;
    --fc-button-border-color: #d1d5db;
    --fc-button-hover-bg-color: #f3f4f6;
    --fc-button-hover-border-color: #9ca3af;
    --fc-button-active-bg-color: #e5e7eb;
    --fc-today-bg-color: #fef3c7;
    --fc-event-text-color: #ffffff;
}

.dark .calendar-container {
    --fc-border-color: #374151;
    --fc-button-text-color: #d1d5db;
    --fc-button-bg-color: #1f2937;
    --fc-button-border-color: #4b5563;
    --fc-button-hover-bg-color: #374151;
    --fc-button-hover-border-color: #6b7280;
    --fc-button-active-bg-color: #4b5563;
    --fc-today-bg-color: #451a03;
    --fc-page-bg-color: #111827;
    --fc-neutral-bg-color: #1f2937;
}

.fc {
    font-family: inherit;
}

.fc-toolbar {
    margin-bottom: 1rem;
}

.fc-toolbar-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
}

.dark .fc-toolbar-title {
    color: #f9fafb;
}

.fc-button {
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}

.fc-event {
    border: none;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.25rem;
}

.fc-daygrid-event {
    margin-top: 1px;
    margin-bottom: 1px;
}

.fc-timegrid-event {
    border-radius: 0.25rem;
}

.fc-day-today {
    background-color: var(--fc-today-bg-color) !important;
}

.fc-scrollgrid {
    border: 1px solid var(--fc-border-color);
    border-radius: 0.5rem;
}

.fc th {
    background-color: #f9fafb;
    border-color: var(--fc-border-color);
}

.dark .fc th {
    background-color: #1f2937;
    color: #d1d5db;
}

.fc td {
    border-color: var(--fc-border-color);
}

.dark .fc td {
    color: #d1d5db;
}

.fc-col-header-cell {
    font-weight: 600;
    color: #374151;
}

.dark .fc-col-header-cell {
    color: #d1d5db;
}

.fc-daygrid-day-number {
    color: #374151;
    font-weight: 500;
}

.dark .fc-daygrid-day-number {
    color: #d1d5db;
}
</style>
