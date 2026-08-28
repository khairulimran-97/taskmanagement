<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import { DateFormatter, type DateValue, getLocalTimeZone, today } from '@internationalized/date';
import { Calendar, Loader2, Plus } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [project: any];
}>();

const df = new DateFormatter('en-US', {
    dateStyle: 'long',
});

const dateItems = [
    { value: 0, label: 'Today' },
    { value: 1, label: 'Tomorrow' },
    { value: 3, label: 'In 3 days' },
    { value: 7, label: 'In a week' },
];

// Default project color: matches the app's pine accent
const DEFAULT_COLOR = '#1D5D4B';

// Curated, muted swatches so arbitrary hues don't fight the UI
const colorPresets = ['#1D5D4B', '#3A5F8A', '#6D5A9E', '#A34A5E', '#B0762B', '#64748B'];

const isSubmitting = ref(false);
const startDateValue = ref<DateValue>();
const dueDateValue = ref<DateValue>();

const form = reactive({
    name: '',
    description: '',
    color: DEFAULT_COLOR,
    status: 'active',
    priority: 'medium',
    due_date: '',
    start_date: '',
    sort_order: null,
});

// Form errors
const errors = ref<Record<string, string>>({});

watch(startDateValue, (newValue) => {
    if (newValue) {
        form.start_date = newValue.toString();
    } else {
        form.start_date = '';
    }
});

watch(dueDateValue, (newValue) => {
    if (newValue) {
        form.due_date = newValue.toString();
    } else {
        form.due_date = '';
    }
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

const resetForm = () => {
    form.name = '';
    form.description = '';
    form.color = DEFAULT_COLOR;
    form.status = 'active';
    form.priority = 'medium';
    form.due_date = '';
    form.start_date = '';
    form.sort_order = null;
    startDateValue.value = undefined;
    dueDateValue.value = undefined;
    errors.value = {};
};

const closeDialog = () => {
    emit('update:open', false);
};

const submitForm = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;
    errors.value = {};

    router.post(route('projects.store'), form, {
        preserveScroll: true,
        onSuccess: (page) => {
            closeDialog();
            emit('success', page.props.project);
        },
        onError: (pageErrors) => {
            errors.value = pageErrors;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogTrigger as-child>
            <Button variant="default" @click="resetForm">
                <Plus class="size-4" />
                New project
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>Create project</DialogTitle>
                <DialogDescription> Add a project to organize related tasks. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="grid gap-4 py-4">
                <!-- Project Name -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="create-name" class="text-right">Name *</Label>
                    <div class="col-span-3">
                        <Input id="create-name" v-model="form.name" placeholder="Enter project name" :class="{ 'border-destructive': errors.name }" />
                        <p v-if="errors.name" class="text-destructive mt-1 text-sm">{{ errors.name }}</p>
                    </div>
                </div>

                <!-- Description -->
                <div class="grid grid-cols-4 items-start gap-4">
                    <Label for="create-description" class="pt-2 text-right">Description</Label>
                    <div class="col-span-3">
                        <Textarea
                            id="create-description"
                            v-model="form.description"
                            placeholder="Enter project description"
                            :class="{ 'border-destructive': errors.description }"
                        />
                        <p v-if="errors.description" class="text-destructive mt-1 text-sm">{{ errors.description }}</p>
                    </div>
                </div>

                <!-- Priority -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="create-priority" class="text-right">Priority</Label>
                    <div class="col-span-3">
                        <Select v-model="form.priority">
                            <SelectTrigger>
                                <SelectValue placeholder="Select priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">Low</SelectItem>
                                <SelectItem value="medium">Medium</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Status -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="create-status" class="text-right">Status</Label>
                    <div class="col-span-3">
                        <Select v-model="form.status">
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="paused">Paused</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="archived">Archived</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Color -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="create-color" class="text-right">Color</Label>
                    <div class="col-span-3 flex items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="preset in colorPresets"
                                :key="preset"
                                type="button"
                                :aria-label="`Use color ${preset}`"
                                class="ring-border focus-visible:ring-ring/50 size-6 cursor-pointer rounded-full ring-1 transition-transform duration-150 hover:scale-110 focus-visible:ring-2 focus-visible:outline-none"
                                :class="{ 'ring-ring ring-2': form.color.toLowerCase() === preset.toLowerCase() }"
                                :style="`background-color: ${preset}`"
                                @click="form.color = preset"
                            ></button>
                        </div>
                        <Input id="create-color" v-model="form.color" type="color" aria-label="Custom color" class="h-8 w-12 cursor-pointer p-1" />
                    </div>
                </div>

                <!-- Start Date -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="create-start-date" class="text-right">Start date</Label>
                    <div class="col-span-3">
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    :class="[
                                        'w-full justify-start text-left font-normal',
                                        !startDateValue && 'text-muted-foreground',
                                        errors.start_date && 'border-destructive',
                                    ]"
                                >
                                    <Calendar class="size-4" />
                                    {{ startDateValue ? df.format(startDateValue.toDate(getLocalTimeZone())) : 'Pick a start date' }}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="flex w-auto flex-col gap-y-2 p-2">
                                <Select
                                    @update:model-value="
                                        (v) => {
                                            if (!v) return;
                                            startDateValue = today(getLocalTimeZone()).add({ days: Number(v) });
                                        }
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="item in dateItems" :key="item.value" :value="item.value.toString()">
                                            {{ item.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <CalendarComponent v-model="startDateValue" />
                            </PopoverContent>
                        </Popover>
                        <p v-if="errors.start_date" class="text-destructive mt-1 text-sm">{{ errors.start_date }}</p>
                    </div>
                </div>

                <!-- Due Date -->
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="create-due-date" class="text-right">Due date</Label>
                    <div class="col-span-3">
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="outline"
                                    :class="[
                                        'w-full justify-start text-left font-normal',
                                        !dueDateValue && 'text-muted-foreground',
                                        errors.due_date && 'border-destructive',
                                    ]"
                                >
                                    <Calendar class="size-4" />
                                    {{ dueDateValue ? df.format(dueDateValue.toDate(getLocalTimeZone())) : 'Pick a due date' }}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="flex w-auto flex-col gap-y-2 p-2">
                                <Select
                                    @update:model-value="
                                        (v) => {
                                            if (!v) return;
                                            dueDateValue = today(getLocalTimeZone()).add({ days: Number(v) });
                                        }
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="item in dateItems" :key="item.value" :value="item.value.toString()">
                                            {{ item.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <CalendarComponent v-model="dueDateValue" />
                            </PopoverContent>
                        </Popover>
                        <p v-if="errors.due_date" class="text-destructive mt-1 text-sm">{{ errors.due_date }}</p>
                    </div>
                </div>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="closeDialog" :disabled="isSubmitting"> Cancel </Button>
                <Button type="button" @click="submitForm" :disabled="isSubmitting">
                    <Loader2 v-if="isSubmitting" class="size-4 animate-spin" />
                    {{ isSubmitting ? 'Creating…' : 'Create project' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
