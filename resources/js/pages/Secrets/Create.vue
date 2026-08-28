<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { secretTypes } from '@/lib/secretMeta';
import { router } from '@inertiajs/vue3';
import { LoaderCircle, Plus } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface Props {
    open: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const isSubmitting = ref(false);
const showDiscardConfirm = ref(false);

const form = reactive({
    name: '',
    type: 'db_password',
    value: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});

// Anything typed counts as dirty — closing then would silently lose it
const isDirty = computed(() => form.name !== '' || form.value !== '' || form.notes !== '' || form.type !== 'db_password');

const resetForm = () => {
    form.name = '';
    form.type = 'db_password';
    form.value = '';
    form.notes = '';
    errors.value = {};
};

// Intercept close attempts (Esc, overlay, cancel) to confirm discarding unsaved input
const handleOpenChange = (value: boolean) => {
    if (value) {
        emit('update:open', true);
        return;
    }
    if (isSubmitting.value) return;
    if (isDirty.value) {
        showDiscardConfirm.value = true;
        return;
    }
    emit('update:open', false);
};

const confirmDiscard = () => {
    showDiscardConfirm.value = false;
    resetForm();
    emit('update:open', false);
};

const submitForm = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;
    errors.value = {};

    router.post(route('secrets.store'), form, {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            emit('update:open', false);
            emit('success');
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
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetTrigger as-child>
            <Button variant="default" @click="resetForm">
                <Plus class="size-4" />
                New secret
            </Button>
        </SheetTrigger>

        <SheetContent side="right" class="w-full gap-0 sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>New secret</SheetTitle>
                <SheetDescription> Store a password, API token, or any secret. Values are encrypted before they hit the database. </SheetDescription>
            </SheetHeader>

            <form id="create-secret-form" @submit.prevent="submitForm" class="flex-1 space-y-5 overflow-y-auto px-4">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="create-secret-name">
                        <span>Name<span aria-hidden="true" class="text-destructive">*</span></span>
                        <span class="sr-only">(required)</span>
                    </Label>
                    <Input
                        id="create-secret-name"
                        v-model="form.name"
                        placeholder="e.g. Prod DB password"
                        :aria-invalid="errors.name ? true : undefined"
                        :aria-describedby="errors.name ? 'create-secret-name-error' : undefined"
                    />
                    <InputError id="create-secret-name-error" :message="errors.name" />
                </div>

                <!-- Type -->
                <div class="space-y-2">
                    <Label for="create-secret-type">Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="create-secret-type" class="w-full">
                            <SelectValue placeholder="Select type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="t in secretTypes" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Value -->
                <div class="space-y-2">
                    <Label for="create-secret-value">
                        <span>Secret<span aria-hidden="true" class="text-destructive">*</span></span>
                        <span class="sr-only">(required)</span>
                    </Label>
                    <Textarea
                        id="create-secret-value"
                        v-model="form.value"
                        rows="14"
                        spellcheck="false"
                        placeholder="Paste the password, token, full .env, or JSON blob…"
                        class="min-h-64 font-mono text-sm"
                        :aria-invalid="errors.value ? true : undefined"
                        :aria-describedby="errors.value ? 'create-secret-value-error' : undefined"
                    />
                    <InputError id="create-secret-value-error" :message="errors.value" />
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="create-secret-notes">Notes</Label>
                    <Textarea
                        id="create-secret-notes"
                        v-model="form.notes"
                        placeholder="Optional: host, port, username, URL…"
                        :aria-invalid="errors.notes ? true : undefined"
                        :aria-describedby="errors.notes ? 'create-secret-notes-error' : undefined"
                    />
                    <InputError id="create-secret-notes-error" :message="errors.notes" />
                </div>
            </form>

            <SheetFooter class="flex-col-reverse sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" :disabled="isSubmitting" @click="handleOpenChange(false)"> Cancel </Button>
                <Button type="submit" form="create-secret-form" :disabled="isSubmitting">
                    <LoaderCircle v-if="isSubmitting" class="size-4 animate-spin" />
                    {{ isSubmitting ? 'Saving…' : 'Save secret' }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>

    <!-- Discard-changes confirmation -->
    <AlertDialog :open="showDiscardConfirm" @update:open="showDiscardConfirm = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Discard this secret?</AlertDialogTitle>
                <AlertDialogDescription> You have unsaved changes. Closing now discards them. </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="showDiscardConfirm = false">Keep editing</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive hover:bg-destructive/90 focus-visible:ring-destructive/30 text-white"
                    @click="confirmDiscard"
                >
                    Discard
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
