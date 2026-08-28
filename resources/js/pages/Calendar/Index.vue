<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { CATEGORIES } from '@/lib/eventCategories';
import { BreadcrumbItem, CalendarEvent, FullCalendarEvent } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Calendar as CalendarIcon, Plus, RefreshCw } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

// FullCalendar imports
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';

import CalendarEventDetailDialog from '@/components/calendar/CalendarEventDetailDialog.vue';
import CalendarEventDialog from '@/components/calendar/CalendarEventDialog.vue';

interface Props {
    availableColors: string[];
    events?: CalendarEvent[];
}

const props = defineProps<Props>();

// No breadcrumb on the calendar — it's a full-width workspace
const breadcrumbs = ref<BreadcrumbItem[]>([]);

/* The DB stores a raw hex per category; the UI renders it through theme tokens
   so event colors adapt to light/dark. Data flow keeps the hex untouched. */
const CATEGORY_DISPLAY: Record<string, string> = {
    '#3B82F6': 'var(--chart-2)', // work
    '#10B981': 'var(--success)', // personal
    '#8B5CF6': 'color-mix(in oklch, var(--chart-2) 55%, var(--chart-4) 45%)', // meeting — violet derived from tokens
    '#EF4444': 'var(--destructive)', // deadline
    '#F59E0B': 'var(--warning)', // reminder
    '#6B7280': 'var(--chart-5)', // other
};

const displayColor = (hex?: string | null): string => CATEGORY_DISPLAY[(hex || '').trim().toUpperCase()] || hex || 'var(--primary)';

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

// Events actually delivered to FullCalendar for the visible range —
// keeps the empty-state overlay honest after refetches
const loadedEventCount = ref(0);

// Dynamic event loading function
const loadEvents = async (fetchInfo: any, successCallback: (...args: unknown[]) => void, failureCallback: (...args: unknown[]) => void) => {
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
        loadedEventCount.value = Array.isArray(events) ? events.length : 0;
        successCallback(events);
    } catch (error) {
        toast.error("Couldn't load events", { description: 'Check your connection and try again.' });
        failureCallback(error);
    } finally {
        isLoadingEvents.value = false;
    }
};

// Restore last-used view
const storedView = (typeof localStorage !== 'undefined' && localStorage.getItem('calendar_view')) || 'dayGridMonth';

// Track narrow screens so the calendar toolbar can simplify
const isMobile = ref(typeof window !== 'undefined' && window.innerWidth < 640);
const onResize = () => {
    isMobile.value = window.innerWidth < 640;
};

// FullCalendar only styles the active view button; mirror it for assistive tech
const VIEW_TYPES = ['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listWeek'];
const syncViewButtonAria = () => {
    VIEW_TYPES.forEach((view) => {
        document.querySelectorAll(`.fc .fc-${view}-button`).forEach((btn) => {
            btn.setAttribute('aria-pressed', String(btn.classList.contains('fc-button-active')));
        });
    });
};

// Calendar options with dynamic event loading
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, listPlugin],
    headerToolbar: isMobile.value
        ? { left: 'prev,next', center: 'title', right: 'today' }
        : {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
          },
    footerToolbar: isMobile.value ? { center: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' } : false,
    initialView: storedView,
    height: '100%',
    expandRows: true,
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
        nextTick(syncViewButtonAria);
    },

    eventDidMount: (info: any) => {
        // Hover preview via native title
        const desc = info.event.extendedProps?.description;
        const time = info.event.allDay ? 'All day' : info.event.start?.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        info.el.setAttribute('title', `${info.event.title}\n${time}${desc ? '\n' + desc : ''}`);

        // Re-skin the chip: FC inlines the stored hex, override with the token color
        const el = info.el as HTMLElement;
        const display = displayColor(info.event.backgroundColor);
        if (el.classList.contains('fc-list-event')) {
            const dot = el.querySelector('.fc-list-event-dot') as HTMLElement | null;
            if (dot) dot.style.borderColor = display;
        } else {
            el.style.backgroundColor = display;
            el.style.borderColor = display;
        }
    },

    // Event styling
    eventDisplay: 'block',
    eventTextColor: 'var(--primary-foreground)',

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
        },
    },
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
                toast.error('Event not rescheduled', { description: 'The change was reverted.' });
            },
            onSuccess: () => {
                toast.success('Event rescheduled');
                afterEventChange();
            },
        });
    } catch {
        dropInfo.revert();
        toast.error('Event not rescheduled', { description: 'The change was reverted.' });
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
                toast.error('Event not rescheduled', { description: 'The change was reverted.' });
            },
            onSuccess: () => {
                toast.success('Event rescheduled');
                afterEventChange();
            },
        });
    } catch {
        resizeInfo.revert();
        toast.error('Event not rescheduled', { description: 'The change was reverted.' });
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

// Flatten server validation errors into one toast description
const errorSummary = (errors: Record<string, string>): string => {
    const messages = [];
    if (errors.title) messages.push(errors.title);
    if (errors.start_date) messages.push(errors.start_date);
    if (errors.end_date) messages.push(errors.end_date);
    return messages.join('\n');
};

// Create new event
const createEvent = (eventData: any) => {
    isSubmitting.value = true;

    router.post(route('calendar.store'), eventData, {
        preserveScroll: true,
        onSuccess: async () => {
            isEventDialogOpen.value = false;
            quickAddOpen.value = false;
            selectedDate.value = null;
            resetForm();
            totalEventCount.value++;
            toast.success('Event created');
            await afterEventChange();
        },
        onError: (errors) => {
            const description = errorSummary(errors);
            toast.error('Event not created', description ? { description } : undefined);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
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
            toast.success('Event updated');
            await afterEventChange();
        },
        onError: (errors) => {
            const description = errorSummary(errors);
            toast.error('Event not updated', description ? { description } : undefined);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

// Delete event
const deleteEvent = (eventId: string) => {
    router.delete(route('calendar.destroy', eventId), {
        preserveScroll: true,
        onSuccess: async () => {
            isEventDetailDialogOpen.value = false;
            selectedEvent.value = null;
            toast.success('Event deleted');
            await afterEventChange();
        },
        onError: () => {
            toast.error('Event not deleted', { description: 'Please try again.' });
        },
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
const isLoadingUpcoming = ref(false);
const upcomingFailed = ref(false);

const todayItems = computed(() => upcoming.value.filter((e) => e.is_today));
const laterItems = computed(() => upcoming.value.filter((e) => !e.is_today));

const fetchUpcoming = async () => {
    isLoadingUpcoming.value = true;
    upcomingFailed.value = false;
    try {
        const res = await fetch(route('calendar.api.upcoming'), { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        upcoming.value = data.events || [];
    } catch {
        upcomingFailed.value = true;
    } finally {
        isLoadingUpcoming.value = false;
    }
};

// Only claim "no events" when every source agrees the account is empty
const showEmptyState = computed(
    () => !isLoadingEvents.value && totalEventCount.value === 0 && loadedEventCount.value === 0 && upcoming.value.length === 0,
);

const relativeDay = (dateStr: string, isToday: boolean): string => {
    if (isToday) return 'Today';
    const d = new Date(dateStr);
    const now = new Date();
    const diff = Math.round(
        (new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime() - new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()) /
            86400000,
    );
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
        textColor: 'var(--primary-foreground)',
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

const closeQuickAdd = () => {
    if (isSubmitting.value) return;
    quickAddOpen.value = false;
};

const submitQuickAdd = () => {
    const title = quickAddTitle.value.trim();
    if (!title || !quickAddDate.value || isSubmitting.value) return;
    const start = quickAddDate.value.start;
    const pad = (n: number) => n.toString().padStart(2, '0');
    const dateStr = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`;
    const allDay = quickAddDate.value.allDay;
    const startTime = allDay ? '00:00' : `${pad(start.getHours())}:${pad(start.getMinutes())}`;
    // Popover stays open while the request runs; createEvent closes it on success
    createEvent({
        title,
        description: '',
        color: CATEGORIES[0].color,
        all_day: allDay,
        start_date: `${dateStr}T${startTime}:00`,
    });
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
        case 't':
            api.today();
            break;
        case 'arrowleft':
            api.prev();
            break;
        case 'arrowright':
            api.next();
            break;
        case 'm':
            api.changeView('dayGridMonth');
            break;
        case 'w':
            api.changeView('timeGridWeek');
            break;
        case 'd':
            api.changeView('timeGridDay');
            break;
        case 'l':
            api.changeView('listWeek');
            break;
        default:
            return;
    }
};

onMounted(() => {
    fetchUpcoming();
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
});
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
});

// Refresh agenda whenever events change
const afterEventChange = async () => {
    await refreshCalendar();
    await fetchUpcoming();
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" full-bleed>
        <Head title="Calendar" />

        <div class="flex h-[calc(100vh-3.5rem)] flex-col">
            <!-- Page header -->
            <div class="shrink-0 px-4 pt-4 md:px-6">
                <PageHeader title="Calendar" description="Your events and appointments" :icon="CalendarIcon">
                    <template #actions>
                        <TooltipProvider :delay-duration="300">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :disabled="isLoadingEvents"
                                        aria-label="Refresh events"
                                        class="text-muted-foreground hover:text-foreground"
                                        @click="refreshCalendar"
                                    >
                                        <RefreshCw class="size-4" :class="{ 'animate-spin': isLoadingEvents }" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>Refresh events</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <Button size="sm" class="gap-1.5" @click="openNewEventDialog">
                            <Plus class="size-4" />
                            New event
                        </Button>
                    </template>
                </PageHeader>
            </div>

            <!-- Body: calendar + agenda rail -->
            <div class="flex min-h-0 flex-1">
                <!-- Calendar -->
                <div class="relative flex min-w-0 flex-1 flex-col overflow-auto px-4 pb-4 md:px-6">
                    <!-- Loading overlay -->
                    <div v-if="isLoadingEvents" class="bg-background/60 absolute inset-0 z-10 flex items-center justify-center">
                        <div class="text-muted-foreground flex items-center gap-2 text-sm">
                            <RefreshCw class="size-4 animate-spin" />
                            <span>Loading events…</span>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-if="showEmptyState" class="pointer-events-none absolute inset-0 z-[5] flex items-center justify-center p-6">
                        <div class="bg-card pointer-events-auto w-full max-w-xs rounded-lg shadow-xs">
                            <EmptyState :icon="CalendarIcon" title="No events yet" description="Click any day to add your first event.">
                                <template #action>
                                    <Button size="sm" class="gap-1.5" @click="openNewEventDialog">
                                        <Plus class="size-4" />
                                        New event
                                    </Button>
                                </template>
                            </EmptyState>
                        </div>
                    </div>

                    <FullCalendar ref="calendarRef" :options="calendarOptions" class="calendar-container" />
                </div>

                <!-- Agenda rail -->
                <aside class="border-border bg-muted/20 hidden w-80 shrink-0 flex-col border-l lg:flex">
                    <div class="border-border border-b px-4 py-3">
                        <h2 class="text-foreground text-sm font-semibold tracking-tight">Up next</h2>
                        <p class="text-muted-foreground text-xs">Today and the next 7 days</p>
                    </div>

                    <div class="flex-1 space-y-5 overflow-y-auto px-4 py-4">
                        <!-- Loading skeleton -->
                        <div v-if="isLoadingUpcoming && !upcoming.length" class="space-y-1.5" aria-hidden="true">
                            <div v-for="n in 3" :key="n" class="bg-muted/60 h-11 animate-pulse rounded-lg"></div>
                        </div>

                        <!-- Load failure -->
                        <div v-else-if="upcomingFailed" class="flex flex-col items-center py-10 text-center">
                            <p class="text-muted-foreground text-sm">Couldn't load upcoming events</p>
                            <Button variant="outline" size="sm" class="mt-3" @click="fetchUpcoming"> Try again </Button>
                        </div>

                        <template v-else>
                            <!-- Today -->
                            <div v-if="todayItems.length">
                                <p class="text-primary mb-2 text-xs font-medium">Today</p>
                                <div class="space-y-1.5">
                                    <button
                                        v-for="e in todayItems"
                                        :key="e.id"
                                        @click="openFromRail(e)"
                                        class="group hover:border-border hover:bg-card focus-visible:ring-ring/50 flex w-full cursor-pointer items-start gap-2.5 rounded-lg border border-transparent px-2 py-2.5 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full" :style="{ backgroundColor: displayColor(e.color) }"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-foreground group-hover:text-primary truncate text-sm font-medium">{{ e.title }}</p>
                                            <p class="text-muted-foreground text-xs tabular-nums">{{ eventTimeLabel(e) }}</p>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Upcoming -->
                            <div v-if="laterItems.length">
                                <p class="text-muted-foreground mb-2 text-xs font-medium">Upcoming</p>
                                <div class="space-y-1.5">
                                    <button
                                        v-for="e in laterItems"
                                        :key="e.id"
                                        @click="openFromRail(e)"
                                        class="group hover:border-border hover:bg-card focus-visible:ring-ring/50 flex w-full cursor-pointer items-start gap-2.5 rounded-lg border border-transparent px-2 py-2.5 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        <span class="mt-1.5 size-2 shrink-0 rounded-full" :style="{ backgroundColor: displayColor(e.color) }"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-foreground group-hover:text-primary truncate text-sm font-medium">{{ e.title }}</p>
                                            <p class="text-muted-foreground text-xs tabular-nums">
                                                {{ relativeDay(e.start_date || e.start, false) }} · {{ eventTimeLabel(e) }}
                                            </p>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Empty -->
                            <div v-if="!todayItems.length && !laterItems.length" class="flex flex-col items-center py-10 text-center">
                                <CalendarIcon class="text-muted-foreground/40 mb-2 size-6" />
                                <p class="text-muted-foreground text-sm">Nothing in the next 7 days</p>
                            </div>
                        </template>
                    </div>

                    <!-- Legend + shortcuts (pinned footer) -->
                    <div class="border-border bg-muted/30 mt-auto space-y-3 border-t px-4 py-3">
                        <div>
                            <p class="text-muted-foreground mb-2 text-xs font-medium">Categories</p>
                            <div class="flex flex-wrap gap-x-3 gap-y-1.5">
                                <span v-for="c in CATEGORIES" :key="c.key" class="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                                    <span class="size-2 rounded-full" :style="{ backgroundColor: displayColor(c.color) }"></span>
                                    {{ c.label }}
                                </span>
                            </div>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">t</kbd> today ·
                            <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">←</kbd>
                            <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">→</kbd> navigate
                            · <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">m</kbd>
                            <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">w</kbd>
                            <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">d</kbd>
                            <kbd class="border-border bg-card text-foreground rounded border px-1 font-sans text-[10px] font-medium">l</kbd> views
                        </p>
                    </div>
                </aside>
            </div>

            <!-- Mobile "Up next" strip (agenda rail equivalent below lg) -->
            <div class="border-border bg-card shrink-0 border-t px-4 py-3 lg:hidden">
                <p class="text-muted-foreground text-xs font-medium">Up next</p>
                <div v-if="upcoming.length" class="mt-2 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="e in upcoming"
                        :key="e.id"
                        @click="openFromRail(e)"
                        class="border-border bg-background hover:bg-muted/60 focus-visible:ring-ring/50 inline-flex min-h-10 shrink-0 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span class="size-2 shrink-0 rounded-full" :style="{ backgroundColor: displayColor(e.color) }"></span>
                        <span class="text-foreground max-w-40 truncate text-sm font-medium">{{ e.title }}</span>
                        <span class="text-muted-foreground text-xs whitespace-nowrap tabular-nums">
                            {{ relativeDay(e.start_date || e.start, e.is_today) }} · {{ eventTimeLabel(e) }}
                        </span>
                    </button>
                </div>
                <p v-else class="text-muted-foreground mt-2 text-sm">Nothing in the next 7 days</p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                    <span v-for="c in CATEGORIES" :key="c.key" class="text-muted-foreground inline-flex items-center gap-1 text-[11px]">
                        <span class="size-1.5 rounded-full" :style="{ backgroundColor: displayColor(c.color) }"></span>
                        {{ c.label }}
                    </span>
                </div>
            </div>

            <!-- Quick-add popover -->
            <Transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-150"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="quickAddOpen"
                    class="bg-foreground/20 fixed inset-0 z-50 flex items-start justify-center pt-32"
                    @click.self="closeQuickAdd"
                    @keydown.esc.prevent="closeQuickAdd"
                >
                    <div role="dialog" aria-label="Quick add event" class="border-border bg-popover w-full max-w-sm rounded-lg border p-4 shadow-md">
                        <p class="text-muted-foreground mb-2 text-xs font-medium">
                            New event · {{ quickAddDate ? relativeDay(quickAddDate.start.toISOString(), false) : '' }}
                        </p>
                        <input
                            ref="quickAddInput"
                            v-model="quickAddTitle"
                            type="text"
                            placeholder="Event title…"
                            :disabled="isSubmitting"
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm transition-colors duration-150 outline-none focus-visible:ring-2 disabled:opacity-50"
                            @keydown.enter.prevent="submitQuickAdd"
                        />
                        <div class="mt-3 flex items-center justify-between">
                            <button
                                type="button"
                                :disabled="isSubmitting"
                                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 -ml-1.5 inline-flex min-h-8 cursor-pointer items-center rounded-md px-1.5 text-xs transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
                                @click="escalateQuickAdd"
                            >
                                More options
                            </button>
                            <div class="flex gap-2">
                                <Button variant="ghost" size="sm" :disabled="isSubmitting" @click="closeQuickAdd">Cancel</Button>
                                <Button size="sm" :disabled="!quickAddTitle.trim() || isSubmitting" @click="submitQuickAdd">
                                    {{ isSubmitting ? 'Adding…' : 'Add event' }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Event Creation/Edit Dialog -->
        <CalendarEventDialog
            :is-open="isEventDialogOpen"
            :selected-date="selectedDate"
            :editing-event="editingEvent"
            :available-colors="availableColors"
            :is-submitting="isSubmitting"
            @close="
                isEventDialogOpen = false;
                resetForm();
            "
            @create="createEvent"
            @update="updateEvent"
        />

        <!-- Event Detail Dialog -->
        <CalendarEventDetailDialog
            :is-open="isEventDetailDialogOpen"
            :event="selectedEvent"
            @close="
                isEventDetailDialogOpen = false;
                selectedEvent = null;
            "
            @edit="handleEditEvent"
            @delete="deleteEvent"
        />
    </AppLayout>
</template>

<style scoped>
/* FullCalendar chrome bound to theme tokens — flips with light/dark automatically */
.calendar-container {
    --fc-border-color: var(--border);
    --fc-button-text-color: var(--foreground);
    --fc-button-bg-color: var(--background);
    --fc-button-border-color: var(--border);
    --fc-button-hover-bg-color: var(--accent);
    --fc-button-hover-border-color: var(--border);
    --fc-button-active-bg-color: var(--muted);
    --fc-today-bg-color: color-mix(in srgb, var(--primary) 7%, transparent);
    --fc-highlight-color: color-mix(in srgb, var(--primary) 8%, transparent);
    --fc-event-text-color: var(--primary-foreground);
    --fc-neutral-bg-color: var(--muted);
    --fc-page-bg-color: var(--background);
    flex: 1 1 auto;
    min-height: 0;
}

:deep(.fc) {
    font-family: inherit;
    height: 100%;
}

:deep(.fc-toolbar) {
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

:deep(.fc-toolbar-title) {
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--foreground);
}

@media (min-width: 640px) {
    :deep(.fc-toolbar-title) {
        font-size: 1.125rem;
    }
}

:deep(.fc-footer-toolbar) {
    margin-top: 0.75rem;
}

/* Toolbar buttons mirror the app's outline Button (border + bg-background + shadow-xs) */
:deep(.fc-button) {
    display: inline-flex;
    align-items: center;
    height: 2rem;
    padding: 0 0.75rem;
    border: 1px solid var(--border) !important;
    border-radius: calc(var(--radius) - 2px) !important;
    background-color: var(--background) !important;
    color: var(--foreground) !important;
    font-size: 0.8125rem;
    font-weight: 500;
    text-transform: capitalize;
    box-shadow: 0 1px 2px 0 color-mix(in srgb, var(--foreground) 5%, transparent) !important;
    transition:
        background-color 150ms,
        border-color 150ms,
        color 150ms;
    cursor: pointer;
}

:deep(.fc-button:hover) {
    background-color: var(--accent) !important;
    color: var(--accent-foreground) !important;
}

:deep(.fc-button:focus) {
    box-shadow: 0 1px 2px 0 color-mix(in srgb, var(--foreground) 5%, transparent) !important;
    outline: none;
}

:deep(.fc-button:focus-visible) {
    outline: 2px solid color-mix(in srgb, var(--ring) 50%, transparent);
    outline-offset: 1px;
}

:deep(.fc-button:disabled) {
    opacity: 0.5;
    cursor: default;
    box-shadow: none !important;
}

/* Active view: quiet primary tint, matching the app's soft-badge status language */
:deep(.fc-button-active) {
    background-color: color-mix(in srgb, var(--primary) 10%, transparent) !important;
    color: var(--primary) !important;
    font-weight: 600;
    box-shadow: none !important;
}

/* Button groups join into one segmented outline control (FC supplies the -1px overlap) */
:deep(.fc-button-group > .fc-button) {
    border-radius: 0 !important;
}

:deep(.fc-button-group > .fc-button:first-child) {
    border-top-left-radius: calc(var(--radius) - 2px) !important;
    border-bottom-left-radius: calc(var(--radius) - 2px) !important;
}

:deep(.fc-button-group > .fc-button:last-child) {
    border-top-right-radius: calc(var(--radius) - 2px) !important;
    border-bottom-right-radius: calc(var(--radius) - 2px) !important;
}

:deep(.fc-button-group > .fc-button:hover),
:deep(.fc-button-group > .fc-button:focus-visible),
:deep(.fc-button-group > .fc-button-active) {
    position: relative;
    z-index: 1;
}

/* Gap between the toolbar chunks (prev/next | title | views) */
:deep(.fc-toolbar > * > :not(:first-child)) {
    margin-left: 0.5rem;
}

/* Event chips */
:deep(.fc-event) {
    border: none;
    border-radius: calc(var(--radius) - 2px);
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.375rem;
    cursor: pointer;
    transition: opacity 150ms;
}

:deep(.fc-event:hover) {
    opacity: 0.85;
}

:deep(.fc-event:focus-visible) {
    outline: 2px solid var(--ring);
    outline-offset: 1px;
}

:deep(.fc-daygrid-event) {
    margin-top: 1px;
    margin-bottom: 1px;
}

:deep(.fc-day-today) {
    background-color: var(--fc-today-bg-color) !important;
}

/* Primary pill on today's date number */
:deep(.fc-daygrid-day.fc-day-today .fc-daygrid-day-number) {
    background-color: var(--primary);
    color: var(--primary-foreground);
    border-radius: 9999px;
    min-width: 1.25rem;
    height: 1.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0.25rem;
    padding: 0 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
}

:deep(.fc-scrollgrid),
:deep(.fc-list) {
    border: 1px solid var(--border);
    border-radius: var(--radius);
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
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--muted-foreground);
    padding: 0.5rem 0;
}

:deep(.fc-daygrid-day-number),
:deep(.fc-timegrid-axis-cushion),
:deep(.fc-timegrid-slot-label-cushion),
:deep(.fc-list-day-text),
:deep(.fc-list-event-title) {
    color: var(--foreground);
    font-weight: 500;
    font-variant-numeric: tabular-nums;
}

:deep(.fc-list-event:hover td) {
    background-color: var(--accent);
}

:deep(.fc-highlight) {
    background-color: color-mix(in srgb, var(--primary) 8%, transparent);
}
</style>
