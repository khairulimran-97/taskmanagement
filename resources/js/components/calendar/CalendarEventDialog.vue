<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { CATEGORIES, isCustomColor } from '@/lib/eventCategories';
import { Calendar, Check, Clock, Palette } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';

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

/* The DB stores a raw hex per category; the UI renders it through theme tokens
   so swatches adapt to light/dark. The submitted value stays the hex. */
const CATEGORY_DISPLAY: Record<string, string> = {
    '#3B82F6': 'var(--chart-2)', // work
    '#10B981': 'var(--success)', // personal
    '#8B5CF6': 'color-mix(in oklch, var(--chart-2) 55%, var(--chart-4) 45%)', // meeting — violet derived from tokens
    '#EF4444': 'var(--destructive)', // deadline
    '#F59E0B': 'var(--warning)', // reminder
    '#6B7280': 'var(--chart-5)', // other
};

const displayColor = (hex?: string | null): string => CATEGORY_DISPLAY[(hex || '').trim().toUpperCase()] || hex || 'var(--primary)';

// Form data
const form = reactive({
    title: '',
    description: '',
    start_date: '',
    start_time: '',
    end_date: '',
    end_time: '',
    color: CATEGORIES[0].color,
    all_day: false,
});

// Force re-render key for Switch component
const switchKey = ref(0);

// Whether the user is using a custom color vs a named category
const useCustom = ref(false);

// Form validation errors
const errors = ref<Record<string, string>>({});

// Dialog title based on editing state
const dialogTitle = computed(() => {
    return props.editingEvent ? 'Edit event' : 'New event';
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
    form.color = CATEGORIES[0].color;
    form.all_day = false;
    errors.value = {};
    useCustom.value = false;

    // Force Switch component re-render
    switchKey.value++;
};

// Populate form with event data (fixed for timezone issues)
const populateForm = (event: any) => {
    form.title = event.title || '';
    form.description = event.extendedProps?.description || '';
    form.color = event.backgroundColor || CATEGORIES[0].color;
    form.all_day = event.allDay || false;
    useCustom.value = isCustomColor(form.color);

    if (event.start) {
        const startDate = new Date(event.start);
        form.start_date = toLocalDateString(startDate);
        if (!form.all_day) {
            form.start_time = toLocalTimeString(startDate);
        }
    }

    if (event.end) {
        const endDate = new Date(event.end);
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
watch(
    () => props.isOpen,
    async (isOpen) => {
        if (isOpen) {
            resetForm();

            // Use nextTick to ensure DOM updates before populating
            await nextTick();

            if (props.editingEvent) {
                populateForm(props.editingEvent);
            } else if (props.selectedDate) {
                populateFromSelectedDate(props.selectedDate);
            }

            // Another nextTick to ensure form population completes
            await nextTick();
        }
    },
);

// Watch the editingEvent prop specifically
watch(
    () => props.editingEvent,
    (newEvent) => {
        if (newEvent && props.isOpen) {
            populateForm(newEvent);
        }
    },
);

// Handle all-day toggle
watch(
    () => form.all_day,
    (isAllDay) => {
        if (isAllDay) {
            form.start_time = '';
            form.end_time = '';
        } else if (form.start_date && !form.start_time) {
            form.start_time = '09:00';
            form.end_time = '10:00';
        }
    },
);

// Validate form
const validateForm = (): boolean => {
    errors.value = {};

    if (!form.title.trim()) {
        errors.value.title = 'Enter a title for the event.';
    }

    if (!form.start_date) {
        errors.value.start_date = 'Pick a start date.';
    }

    if (!form.all_day && !form.start_time) {
        errors.value.start_time = 'Pick a start time, or switch to all day.';
    }

    if (form.end_date && form.start_date && form.end_date < form.start_date) {
        errors.value.end_date = 'The end date must be after the start date.';
    }

    if (!form.all_day && form.end_date && form.end_time && form.start_date === form.end_date && form.end_time <= form.start_time) {
        errors.value.end_time = 'The end time must be after the start time.';
    }

    return Object.keys(errors.value).length === 0;
};

// Build event data for submission
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
    <Sheet :open="isOpen" @update:open="(open) => !open && handleCancel()">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-border border-b px-5 py-4 text-left">
                <SheetTitle class="text-base font-semibold tracking-tight">{{ dialogTitle }}</SheetTitle>
                <SheetDescription>
                    {{ editingEvent ? 'Update the event details below.' : 'Fill in the details to add an event.' }}
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="handleSubmit" class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                <!-- Title -->
                <div class="space-y-2">
                    <Label for="event-title">Title</Label>
                    <Input
                        id="event-title"
                        v-model="form.title"
                        placeholder="Event title"
                        :aria-invalid="!!errors.title"
                        :class="{ 'border-destructive': errors.title }"
                    />
                    <InputError :message="errors.title" />
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="event-description"> Description <span class="text-muted-foreground font-normal">(optional)</span> </Label>
                    <Textarea id="event-description" v-model="form.description" placeholder="Add any details" rows="3" />
                </div>

                <!-- All-day toggle -->
                <div class="flex items-center space-x-3">
                    <Switch :key="switchKey" id="all-day" :model-value="form.all_day" @update:model-value="(value) => (form.all_day = value)" />
                    <Label for="all-day" class="cursor-pointer">All day</Label>
                </div>

                <!-- Date and time -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Start date -->
                    <div class="space-y-2">
                        <Label for="start-date">Start date</Label>
                        <div class="relative">
                            <Calendar class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="start-date"
                                type="date"
                                v-model="form.start_date"
                                class="pl-10"
                                :aria-invalid="!!errors.start_date"
                                :class="{ 'border-destructive': errors.start_date }"
                            />
                        </div>
                        <InputError :message="errors.start_date" />
                        <p v-if="form.start_date" class="text-muted-foreground text-xs">
                            {{ formatDate(form.start_date) }}
                        </p>
                    </div>

                    <!-- Start time -->
                    <div v-if="!form.all_day" class="space-y-2">
                        <Label for="start-time">Start time</Label>
                        <div class="relative">
                            <Clock class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="start-time"
                                type="time"
                                v-model="form.start_time"
                                class="pl-10"
                                :aria-invalid="!!errors.start_time"
                                :class="{ 'border-destructive': errors.start_time }"
                            />
                        </div>
                        <InputError :message="errors.start_time" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- End date -->
                    <div class="space-y-2">
                        <Label for="end-date"> End date <span class="text-muted-foreground font-normal">(optional)</span> </Label>
                        <div class="relative">
                            <Calendar class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="end-date"
                                type="date"
                                v-model="form.end_date"
                                class="pl-10"
                                :aria-invalid="!!errors.end_date"
                                :class="{ 'border-destructive': errors.end_date }"
                            />
                        </div>
                        <InputError :message="errors.end_date" />
                        <p v-if="form.end_date" class="text-muted-foreground text-xs">
                            {{ formatDate(form.end_date) }}
                        </p>
                    </div>

                    <!-- End time -->
                    <div v-if="!form.all_day" class="space-y-2">
                        <Label for="end-time"> End time <span class="text-muted-foreground font-normal">(optional)</span> </Label>
                        <div class="relative">
                            <Clock class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                id="end-time"
                                type="time"
                                v-model="form.end_time"
                                class="pl-10"
                                :aria-invalid="!!errors.end_time"
                                :class="{ 'border-destructive': errors.end_time }"
                            />
                        </div>
                        <InputError :message="errors.end_time" />
                    </div>
                </div>

                <!-- Category -->
                <div class="space-y-2">
                    <Label>Category</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="cat in CATEGORIES"
                            :key="cat.key"
                            type="button"
                            :aria-pressed="form.color === cat.color && !useCustom"
                            @click="
                                form.color = cat.color;
                                useCustom = false;
                            "
                            class="focus-visible:ring-ring/50 flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                            :class="
                                form.color === cat.color && !useCustom
                                    ? 'border-primary bg-primary/10 text-foreground'
                                    : 'border-border text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground'
                            "
                        >
                            <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: displayColor(cat.color) }"></span>
                            <component :is="cat.icon" class="size-3.5 shrink-0" />
                            <span class="truncate">{{ cat.label }}</span>
                            <Check v-if="form.color === cat.color && !useCustom" class="text-primary ml-auto size-3.5 shrink-0" />
                        </button>
                    </div>

                    <!-- Custom color escape hatch -->
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 mt-1 -ml-1.5 inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-md px-1.5 text-xs transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        @click="useCustom = !useCustom"
                    >
                        <Palette class="size-3.5" />
                        {{ useCustom ? 'Use a category instead' : 'Custom color' }}
                    </button>
                    <div v-if="useCustom" class="flex items-center gap-2">
                        <Input
                            id="custom-color"
                            type="color"
                            v-model="form.color"
                            class="h-9 w-12 cursor-pointer p-1"
                            aria-label="Custom event color"
                        />
                        <span class="text-muted-foreground text-xs uppercase">{{ form.color }}</span>
                    </div>
                </div>
            </form>

            <SheetFooter class="border-border flex-row justify-end gap-2 border-t px-5 py-4">
                <Button type="button" variant="outline" @click="handleCancel" :disabled="isSubmitting"> Cancel </Button>
                <Button type="button" @click="handleSubmit" :disabled="isSubmitting">
                    <span v-if="isSubmitting">{{ editingEvent ? 'Saving…' : 'Creating…' }}</span>
                    <span v-else>{{ editingEvent ? 'Save changes' : 'Create event' }}</span>
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
