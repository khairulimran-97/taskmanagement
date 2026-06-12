<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, nextTick } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CalendarEvent, FullCalendarEvent } from '@/types';
import { Button } from '@/components/ui/button';
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
    <AppLayout :breadcrumbs="breadcrumbs" full-bleed>
        <Head title="Calendar" />

        <div class="flex h-[calc(100vh-3.5rem)] flex-col">
            <!-- Header bar -->
            <div class="flex items-center justify-between gap-3 border-b border-border bg-card px-4 py-3 md:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/12 text-primary">
                        <CalendarIcon class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-semibold tracking-tight text-foreground">Calendar</h1>
                        <p class="text-xs text-muted-foreground">Manage your personal events and appointments</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        @click="refreshCalendar"
                        variant="outline"
                        size="sm"
                        :disabled="isLoadingEvents"
                        class="h-9 gap-1.5"
                    >
                        <RefreshCw :class="['h-4 w-4', { 'animate-spin': isLoadingEvents }]" />
                        <span class="hidden sm:inline">{{ isLoadingEvents ? 'Loading…' : 'Refresh' }}</span>
                    </Button>
                    <Button @click="openNewEventDialog" size="sm" class="h-9 gap-1.5">
                        <Plus class="h-4 w-4" />
                        <span>New Event</span>
                    </Button>
                </div>
            </div>

            <!-- Calendar -->
            <div class="relative flex-1 overflow-auto px-4 py-4 md:px-6">
                <!-- Loading overlay -->
                <div
                    v-if="isLoadingEvents"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
                >
                    <div class="flex items-center gap-2 text-muted-foreground">
                        <RefreshCw class="h-5 w-5 animate-spin" />
                        <span>Loading events…</span>
                    </div>
                </div>

                <FullCalendar
                    ref="calendarRef"
                    :options="calendarOptions"
                    class="calendar-container"
                />
            </div>
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
/* Calendar container — driven by theme tokens so it flips automatically */
.calendar-container {
    --fc-border-color: var(--border);
    --fc-button-text-color: var(--foreground);
    --fc-button-bg-color: var(--card);
    --fc-button-border-color: var(--border);
    --fc-button-hover-bg-color: var(--accent);
    --fc-button-hover-border-color: var(--primary);
    --fc-button-active-bg-color: var(--primary);
    --fc-today-bg-color: color-mix(in srgb, var(--primary) 10%, transparent);
    --fc-event-text-color: #ffffff;
    --fc-neutral-bg-color: var(--muted);
    --fc-page-bg-color: var(--background);
}

:deep(.fc) {
    font-family: inherit;
}

:deep(.fc-toolbar) {
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

:deep(.fc-toolbar-title) {
    font-family: var(--font-serif);
    font-size: 1.35rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--foreground);
}

:deep(.fc-button) {
    border-radius: 0.5rem !important;
    border: 1px solid var(--border) !important;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 0.45rem 0.85rem;
    text-transform: capitalize;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
    transition: background-color 0.15s, border-color 0.15s, color 0.15s;
}

:deep(.fc-button:hover) {
    border-color: var(--primary) !important;
}

/* Space out buttons within FullCalendar's button groups */
:deep(.fc-button-group) {
    gap: 0.375rem;
}

:deep(.fc-button-group > .fc-button) {
    border-radius: 0.5rem !important;
    margin: 0 !important;
}

/* Gap between the toolbar chunks (prev/next | title | views) */
:deep(.fc-toolbar > * > :not(:first-child)) {
    margin-left: 0.5rem;
}

:deep(.fc-button-primary:not(:disabled).fc-button-active),
:deep(.fc-button-primary:not(:disabled):active) {
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
    color: var(--primary-foreground) !important;
}

:deep(.fc-event) {
    border: none;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.35rem;
    cursor: pointer;
}

:deep(.fc-daygrid-event) {
    margin-top: 1px;
    margin-bottom: 1px;
}

:deep(.fc-timegrid-event) {
    border-radius: 0.375rem;
}

:deep(.fc-day-today) {
    background-color: var(--fc-today-bg-color) !important;
}

:deep(.fc-scrollgrid) {
    border: 1px solid var(--border);
    border-radius: 0.625rem;
    overflow: hidden;
}

:deep(.fc th) {
    background-color: var(--muted);
    border-color: var(--border);
}

:deep(.fc td) {
    border-color: var(--border);
}

:deep(.fc-col-header-cell) {
    font-weight: 600;
    color: var(--muted-foreground);
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.04em;
    padding: 0.6rem 0;
}

:deep(.fc-daygrid-day-number),
:deep(.fc-timegrid-axis-cushion),
:deep(.fc-timegrid-slot-label-cushion),
:deep(.fc-list-day-text),
:deep(.fc-list-event-title) {
    color: var(--foreground);
    font-weight: 500;
}

:deep(.fc-list-event:hover td) {
    background-color: var(--accent);
}

:deep(.fc-event:hover) {
    opacity: 0.85;
    transition: opacity 0.2s ease;
}

:deep(.fc-highlight) {
    background-color: color-mix(in srgb, var(--primary) 12%, transparent);
}

/* Slim scrollbar to match the app */
:deep(.fc-scroller::-webkit-scrollbar) {
    width: 5px;
    height: 5px;
}

:deep(.fc-scroller::-webkit-scrollbar-track) {
    background: transparent;
}

:deep(.fc-scroller::-webkit-scrollbar-thumb) {
    background: color-mix(in srgb, var(--muted-foreground) 30%, transparent);
    border-radius: 9999px;
}

:deep(.fc-scroller::-webkit-scrollbar-thumb:hover) {
    background: color-mix(in srgb, var(--muted-foreground) 50%, transparent);
}
</style>
