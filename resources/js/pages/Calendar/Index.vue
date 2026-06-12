<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, CalendarEvent, FullCalendarEvent } from '@/types';
import { Button } from '@/components/ui/button';
import { Plus, Calendar as CalendarIcon, RefreshCw } from 'lucide-vue-next';
import { CATEGORIES } from '@/lib/eventCategories';

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

// No breadcrumb on the calendar — it's a full-width workspace
const breadcrumbs = ref<BreadcrumbItem[]>([]);

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

// Restore last-used view
const storedView = (typeof localStorage !== 'undefined' && localStorage.getItem('calendar_view')) || 'dayGridMonth';

// Calendar options with dynamic event loading
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
    },
    initialView: storedView,
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

    // Persist the active view
    datesSet: (info: any) => {
        if (typeof localStorage !== 'undefined') localStorage.setItem('calendar_view', info.view.type);
    },

    // Hover preview via native title
    eventDidMount: (info: any) => {
        const desc = info.event.extendedProps?.description;
        const time = info.event.allDay
            ? 'All day'
            : info.event.start?.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        info.el.setAttribute('title', `${info.event.title}\n${time}${desc ? '\n' + desc : ''}`);
    },

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

// Date selection handler → lightweight quick-add
function handleDateSelect(selectInfo: any) {
    openQuickAdd({ start: selectInfo.start, end: selectInfo.end, allDay: selectInfo.allDay });
    calendarRef.value?.getApi?.()?.unselect();
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
                afterEventChange();
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
                afterEventChange();
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
            await afterEventChange();
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
            await afterEventChange();
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
            await afterEventChange();
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

/* ───────────────── Upcoming agenda rail ───────────────── */
const upcoming = ref<any[]>([]);
const totalEventCount = ref<number>(props.events?.length || 0);

const todayItems = computed(() => upcoming.value.filter((e) => e.is_today));
const laterItems = computed(() => upcoming.value.filter((e) => !e.is_today));

const fetchUpcoming = async () => {
    try {
        const res = await fetch(route('calendar.api.upcoming'), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        upcoming.value = data.events || [];
    } catch {
        /* non-fatal */
    }
};

const relativeDay = (dateStr: string, isToday: boolean): string => {
    if (isToday) return 'Today';
    const d = new Date(dateStr);
    const now = new Date();
    const diff = Math.round((new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime() - new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()) / 86400000);
    if (diff === 1) return 'Tomorrow';
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
};

const eventTimeLabel = (e: any): string => {
    if (e.all_day) return 'All day';
    return new Date(e.start_date || e.start).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
};

// Open the detail slideover from a rail item
const openFromRail = (e: any) => {
    selectedEvent.value = {
        id: e.id,
        title: e.title,
        start: e.start_date || e.start,
        end: e.end_date || e.end || undefined,
        allDay: e.all_day,
        backgroundColor: e.color,
        borderColor: e.color,
        textColor: '#ffffff',
        extendedProps: { description: e.description || '' },
    } as any;
    isEventDetailDialogOpen.value = true;
};

/* ───────────────── Quick add ───────────────── */
const quickAddOpen = ref(false);
const quickAddTitle = ref('');
const quickAddDate = ref<{ start: Date; end?: Date; allDay: boolean } | null>(null);
const quickAddInput = ref<HTMLInputElement | null>(null);

const openQuickAdd = (info: { start: Date; end?: Date; allDay: boolean }) => {
    quickAddDate.value = info;
    quickAddTitle.value = '';
    quickAddOpen.value = true;
    nextTick(() => quickAddInput.value?.focus());
};

const submitQuickAdd = () => {
    const title = quickAddTitle.value.trim();
    if (!title || !quickAddDate.value) return;
    const start = quickAddDate.value.start;
    const pad = (n: number) => n.toString().padStart(2, '0');
    const dateStr = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`;
    const allDay = quickAddDate.value.allDay;
    const startTime = allDay ? '00:00' : `${pad(start.getHours())}:${pad(start.getMinutes())}`;
    createEvent({
        title,
        description: '',
        color: '#3B82F6',
        all_day: allDay,
        start_date: `${dateStr}T${startTime}:00`,
    });
    quickAddOpen.value = false;
};

const escalateQuickAdd = () => {
    selectedDate.value = quickAddDate.value
        ? { start: quickAddDate.value.start, end: quickAddDate.value.end, allDay: quickAddDate.value.allDay }
        : null;
    quickAddOpen.value = false;
    editingEvent.value = null;
    isEventDialogOpen.value = true;
};

/* ───────────────── Keyboard navigation ───────────────── */
const onKeydown = (e: KeyboardEvent) => {
    const t = e.target as HTMLElement;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    const api = calendarRef.value?.getApi?.();
    if (!api) return;
    switch (e.key.toLowerCase()) {
        case 't': api.today(); break;
        case 'arrowleft': api.prev(); break;
        case 'arrowright': api.next(); break;
        case 'm': api.changeView('dayGridMonth'); break;
        case 'w': api.changeView('timeGridWeek'); break;
        case 'd': api.changeView('timeGridDay'); break;
        case 'l': api.changeView('listWeek'); break;
        default: return;
    }
};

onMounted(() => {
    fetchUpcoming();
    window.addEventListener('keydown', onKeydown);
});
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

// Refresh agenda whenever events change
const afterEventChange = async () => {
    await afterEventChange();
    await fetchUpcoming();
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

            <!-- Body: calendar + agenda rail -->
            <div class="flex min-h-0 flex-1">
                <!-- Calendar -->
                <div class="relative min-w-0 flex-1 overflow-auto px-4 py-4 md:px-6">
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

                    <!-- Empty state -->
                    <div
                        v-if="totalEventCount === 0 && upcoming.length === 0"
                        class="pointer-events-none absolute inset-0 z-[5] flex items-center justify-center p-6"
                    >
                        <div class="pointer-events-auto flex max-w-xs flex-col items-center rounded-2xl border border-dashed border-border bg-card/80 px-8 py-10 text-center backdrop-blur-sm">
                            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                <CalendarIcon class="h-7 w-7" />
                            </div>
                            <h3 class="font-display text-lg font-semibold text-foreground">No events yet</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Click any day or add your first event to get started.</p>
                            <Button @click="openNewEventDialog" size="sm" class="mt-4 gap-1.5">
                                <Plus class="h-4 w-4" /> Add event
                            </Button>
                        </div>
                    </div>

                    <FullCalendar
                        ref="calendarRef"
                        :options="calendarOptions"
                        class="calendar-container"
                    />
                </div>

                <!-- Agenda rail -->
                <aside class="hidden w-80 shrink-0 flex-col overflow-y-auto border-l border-border bg-muted/20 lg:flex">
                    <div class="border-b border-border px-4 py-3">
                        <h2 class="font-display text-base font-semibold tracking-tight text-foreground">Up next</h2>
                        <p class="text-xs text-muted-foreground">Today and the next 7 days</p>
                    </div>

                    <div class="space-y-5 px-4 py-4">
                        <!-- Today -->
                        <div v-if="todayItems.length">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-primary">Today</p>
                            <div class="space-y-1.5">
                                <button
                                    v-for="e in todayItems"
                                    :key="e.id"
                                    @click="openFromRail(e)"
                                    class="group flex w-full items-start gap-2.5 rounded-lg border border-transparent px-2 py-2 text-left transition-colors hover:border-border hover:bg-card"
                                >
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: e.color }"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-foreground group-hover:text-primary">{{ e.title }}</p>
                                        <p class="text-xs text-muted-foreground">{{ eventTimeLabel(e) }}</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Upcoming -->
                        <div v-if="laterItems.length">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Upcoming</p>
                            <div class="space-y-1.5">
                                <button
                                    v-for="e in laterItems"
                                    :key="e.id"
                                    @click="openFromRail(e)"
                                    class="group flex w-full items-start gap-2.5 rounded-lg border border-transparent px-2 py-2 text-left transition-colors hover:border-border hover:bg-card"
                                >
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: e.color }"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-foreground group-hover:text-primary">{{ e.title }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ relativeDay(e.start_date || e.start, false) }} · {{ eventTimeLabel(e) }}
                                        </p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div v-if="!todayItems.length && !laterItems.length" class="flex flex-col items-center py-10 text-center">
                            <CalendarIcon class="mb-2 h-7 w-7 text-muted-foreground/40" />
                            <p class="text-sm text-muted-foreground">Nothing coming up</p>
                        </div>

                        <!-- Category legend -->
                        <div class="border-t border-border pt-4">
                            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Categories</p>
                            <div class="flex flex-wrap gap-x-3 gap-y-1.5">
                                <span v-for="c in CATEGORIES" :key="c.key" class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: c.color }"></span>
                                    {{ c.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Quick-add popover -->
            <div
                v-if="quickAddOpen"
                class="fixed inset-0 z-50 flex items-start justify-center bg-foreground/10 pt-32"
                @click.self="quickAddOpen = false"
            >
                <div class="w-full max-w-sm rounded-xl border border-border bg-card p-4 shadow-xl">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">
                        New event · {{ quickAddDate ? relativeDay(quickAddDate.start.toISOString(), false) : '' }}
                    </p>
                    <input
                        ref="quickAddInput"
                        v-model="quickAddTitle"
                        type="text"
                        placeholder="Event title…"
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @keydown.enter.prevent="submitQuickAdd"
                        @keydown.esc.prevent="quickAddOpen = false"
                    />
                    <div class="mt-3 flex items-center justify-between">
                        <button class="text-xs text-muted-foreground hover:text-primary" @click="escalateQuickAdd">
                            More options →
                        </button>
                        <div class="flex gap-2">
                            <Button variant="ghost" size="sm" @click="quickAddOpen = false">Cancel</Button>
                            <Button size="sm" :disabled="!quickAddTitle.trim()" @click="submitQuickAdd">Add</Button>
                        </div>
                    </div>
                </div>
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
    --fc-button-hover-border-color: var(--border);
    --fc-button-active-bg-color: var(--muted);
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

/* View switcher (Month/Week/Day/List) as a segmented control — keeps
   saffron reserved for true primary actions like "New Event". */
:deep(.fc-toolbar-chunk:last-child .fc-button-group) {
    gap: 0 !important;
    background-color: var(--muted);
    border: 1px solid var(--border);
    border-radius: 0.625rem;
    padding: 0.25rem;
}

:deep(.fc-toolbar-chunk:last-child .fc-button-group > .fc-button) {
    border: 1px solid transparent !important;
    background-color: transparent !important;
    box-shadow: none !important;
    color: var(--muted-foreground) !important;
    padding: 0.3rem 0.75rem !important;
}

:deep(.fc-toolbar-chunk:last-child .fc-button-group > .fc-button:hover) {
    color: var(--foreground) !important;
    border-color: transparent !important;
}

:deep(.fc-toolbar-chunk:last-child .fc-button-group > .fc-button-active) {
    background-color: var(--card) !important;
    color: var(--foreground) !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
    font-weight: 600 !important;
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

/* Saffron pill on today's date number */
:deep(.fc-daygrid-day.fc-day-today .fc-daygrid-day-number) {
    background-color: var(--primary);
    color: var(--primary-foreground);
    border-radius: 9999px;
    min-width: 1.5rem;
    height: 1.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0.2rem;
    font-weight: 600;
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
