<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CalendarEvent, FullCalendarEvent } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Calendar as CalendarIcon, RefreshCw } from 'lucide-vue-next';

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

// Modal states
const isEventDialogOpen = ref(false);
const isEventDetailDialogOpen = ref(false);
const editingEvent = ref<FullCalendarEvent | null>(null);
const selectedEvent = ref<FullCalendarEvent | null>(null);
const selectedDate = ref<any>(null);
const isSubmitting = ref(false);
const isLoadingEvents = ref(false);

// Dynamic event loading function
const loadEvents = async (fetchInfo: any, successCallback: Function, failureCallback: Function) => {
    isLoadingEvents.value = true;

    try {
        const url = new URL(route('calendar.api.events'), window.location.origin);
        url.searchParams.append('start', fetchInfo.startStr);
        url.searchParams.append('end', fetchInfo.endStr);

        const response = await fetch(url.toString());

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const events = await response.json();
        successCallback(events);
    } catch (error) {
        failureCallback(error);
    } finally {
        isLoadingEvents.value = false;
    }
};

// Calendar options with dynamic event loading
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

    // Use dynamic event loading
    events: loadEvents,

    // Loading state
    loading: (isLoading: boolean) => {
        isLoadingEvents.value = isLoading;
    },

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

// Date selection handler
function handleDateSelect(selectInfo: any) {
    selectedDate.value = {
        start: selectInfo.start,
        end: selectInfo.end,
        allDay: selectInfo.allDay
    };
    editingEvent.value = null;
    isEventDialogOpen.value = true;
}

// Event click handler
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

// Date formatting functions
const formatDateTimeForServer = (date: Date, allDay: boolean = false): string => {
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');

    if (allDay) {
        return `${year}-${month}-${day}T00:00:00`;
    }

    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    const seconds = date.getSeconds().toString().padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
};

// Event drag and drop handler
function handleEventDrop(dropInfo: any) {
    const event = dropInfo.event;
    const updateData: any = {
        all_day: event.allDay,
    };

    try {
        if (event.allDay) {
            updateData.start_date = formatDateTimeForServer(event.start, true);
            if (event.end) {
                const endDate = new Date(event.end);
                endDate.setDate(endDate.getDate() - 1);
                updateData.end_date = `${endDate.getFullYear()}-${(endDate.getMonth() + 1).toString().padStart(2, '0')}-${endDate.getDate().toString().padStart(2, '0')}T23:59:59`;
            }
        } else {
            updateData.start_date = formatDateTimeForServer(event.start);
            if (event.end) {
                updateData.end_date = formatDateTimeForServer(event.end);
            }
        }

        router.patch(route('calendar.update-dates', event.id), updateData, {
            preserveScroll: true,
            onError: () => {
                dropInfo.revert();
            },
            onSuccess: () => {
                refreshCalendar();
            }
        });
    } catch (error) {
        dropInfo.revert();
    }
}

// Event resize handler
function handleEventResize(resizeInfo: any) {
    const event = resizeInfo.event;

    try {
        const updateData: any = {
            all_day: event.allDay,
        };

        if (event.allDay) {
            updateData.start_date = formatDateTimeForServer(event.start, true);
            if (event.end) {
                const endDate = new Date(event.end);
                endDate.setDate(endDate.getDate() - 1);
                updateData.end_date = `${endDate.getFullYear()}-${(endDate.getMonth() + 1).toString().padStart(2, '0')}-${endDate.getDate().toString().padStart(2, '0')}T23:59:59`;
            }
        } else {
            updateData.start_date = formatDateTimeForServer(event.start);
            if (event.end) {
                updateData.end_date = formatDateTimeForServer(event.end);
            }
        }

        router.patch(route('calendar.update-dates', event.id), updateData, {
            preserveScroll: true,
            onError: () => {
                resizeInfo.revert();
            },
            onSuccess: () => {
                refreshCalendar();
            }
        });
    } catch (error) {
        resizeInfo.revert();
    }
}

// Refresh calendar function
const refreshCalendar = async () => {
    await nextTick();
    if (calendarRef.value) {
        const calendarApi = calendarRef.value.getApi();
        calendarApi.refetchEvents();
    }
};

// Create new event
const createEvent = (eventData: any) => {
    isSubmitting.value = true;

    router.post(route('calendar.store'), eventData, {
        preserveScroll: true,
        onSuccess: async () => {
            isEventDialogOpen.value = false;
            selectedDate.value = null;
            resetForm();
            await refreshCalendar();
        },
        onError: (errors) => {
            // Handle validation errors
            const errorMessages = [];
            if (errors.start_date) errorMessages.push(`Start date: ${errors.start_date}`);
            if (errors.end_date) errorMessages.push(`End date: ${errors.end_date}`);
            if (errors.title) errorMessages.push(`Title: ${errors.title}`);

            if (errorMessages.length > 0) {
                alert('Please fix the following errors:\n' + errorMessages.join('\n'));
            }
        },
        onFinish: () => {
            isSubmitting.value = false;
        }
    });
};

// Update existing event
const updateEvent = (eventId: string, eventData: any) => {
    isSubmitting.value = true;

    router.put(route('calendar.update', eventId), eventData, {
        preserveScroll: true,
        onSuccess: async () => {
            isEventDialogOpen.value = false;
            isEventDetailDialogOpen.value = false;
            editingEvent.value = null;
            resetForm();
            await refreshCalendar();
        },
        onError: (errors) => {
            // Handle validation errors
            const errorMessages = [];
            if (errors.start_date) errorMessages.push(`Start date: ${errors.start_date}`);
            if (errors.end_date) errorMessages.push(`End date: ${errors.end_date}`);
            if (errors.title) errorMessages.push(`Title: ${errors.title}`);

            if (errorMessages.length > 0) {
                alert('Please fix the following errors:\n' + errorMessages.join('\n'));
            }
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
        onSuccess: async () => {
            isEventDetailDialogOpen.value = false;
            selectedEvent.value = null;
            await refreshCalendar();
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
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Manage your personal events and appointments
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <Button
                            @click="refreshCalendar"
                            variant="outline"
                            size="sm"
                            :disabled="isLoadingEvents"
                            class="flex items-center space-x-2"
                        >
                            <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoadingEvents }]" />
                            <span>{{ isLoadingEvents ? 'Loading...' : 'Refresh' }}</span>
                        </Button>
                        <Button @click="openNewEventDialog" class="flex items-center space-x-2">
                            <Plus class="h-4 w-4" />
                            <span>New Event</span>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <Card>
                <CardContent class="p-6">
                    <div class="relative">
                        <!-- Loading overlay -->
                        <div
                            v-if="isLoadingEvents"
                            class="absolute inset-0 bg-white/50 dark:bg-gray-900/50 flex items-center justify-center z-10 rounded-lg"
                        >
                            <div class="flex items-center space-x-2 text-gray-600 dark:text-gray-400">
                                <RefreshCw class="h-5 w-5 animate-spin" />
                                <span>Loading events...</span>
                            </div>
                        </div>

                        <FullCalendar
                            ref="calendarRef"
                            :options="calendarOptions"
                            class="calendar-container"
                        />
                    </div>
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

<style scoped>
/* Calendar container styles */
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

/* Dark mode styles */
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

/* FullCalendar component styles */
:deep(.fc) {
    font-family: inherit;
}

:deep(.fc-toolbar) {
    margin-bottom: 1rem;
}

:deep(.fc-toolbar-title) {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111827;
}

.dark :deep(.fc-toolbar-title) {
    color: #f9fafb;
}

:deep(.fc-button) {
    border-radius: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}

:deep(.fc-event) {
    border: none;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.25rem;
    cursor: pointer;
}

:deep(.fc-daygrid-event) {
    margin-top: 1px;
    margin-bottom: 1px;
}

:deep(.fc-timegrid-event) {
    border-radius: 0.25rem;
}

:deep(.fc-day-today) {
    background-color: var(--fc-today-bg-color) !important;
}

:deep(.fc-scrollgrid) {
    border: 1px solid var(--fc-border-color);
    border-radius: 0.5rem;
}

:deep(.fc th) {
    background-color: #f9fafb;
    border-color: var(--fc-border-color);
}

.dark :deep(.fc th) {
    background-color: #1f2937;
    color: #d1d5db;
}

:deep(.fc td) {
    border-color: var(--fc-border-color);
}

.dark :deep(.fc td) {
    color: #d1d5db;
}

:deep(.fc-col-header-cell) {
    font-weight: 600;
    color: #374151;
}

.dark :deep(.fc-col-header-cell) {
    color: #d1d5db;
}

:deep(.fc-daygrid-day-number) {
    color: #374151;
    font-weight: 500;
}

.dark :deep(.fc-daygrid-day-number) {
    color: #d1d5db;
}

/* Event hover effects */
:deep(.fc-event:hover) {
    opacity: 0.8;
    transition: opacity 0.2s ease;
}

/* Selection styles */
:deep(.fc-highlight) {
    background-color: rgba(59, 130, 246, 0.1);
}

/* Scrollbar styles for calendar */
:deep(.fc-scroller::-webkit-scrollbar) {
    width: 8px;
    height: 8px;
}

:deep(.fc-scroller::-webkit-scrollbar-track) {
    background: #f1f1f1;
    border-radius: 4px;
}

:deep(.fc-scroller::-webkit-scrollbar-thumb) {
    background: #c1c1c1;
    border-radius: 4px;
}

:deep(.fc-scroller::-webkit-scrollbar-thumb:hover) {
    background: #a1a1a1;
}

.dark :deep(.fc-scroller::-webkit-scrollbar-track) {
    background: #374151;
}

.dark :deep(.fc-scroller::-webkit-scrollbar-thumb) {
    background: #6b7280;
}

.dark :deep(.fc-scroller::-webkit-scrollbar-thumb:hover) {
    background: #9ca3af;
}
</style>
