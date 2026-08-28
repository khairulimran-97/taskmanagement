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
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { secretTypes } from '@/lib/secretMeta';
import { Secret } from '@/types';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Props {
    open: boolean;
    secret: Secret | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const isSubmitting = ref(false);
const showDiscardConfirm = ref(false);

const form = reactive({
    id: null as number | null,
    name: '',
    type: 'db_password',
    value: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});

watch(
    [() => props.open, () => props.secret],
    ([isOpen, secret]) => {
        if (isOpen && secret) {
            populateForm(secret);
        }
    },
    { immediate: true },
);

const populateForm = (secret: Secret) => {
    form.id = secret.id;
    form.name = secret.name;
    form.type = secret.type || 'other';
    form.value = secret.value;
    form.notes = secret.notes || '';
    errors.value = {};
};

const resetForm = () => {
    form.id = null;
    form.name = '';
    form.type = 'db_password';
    form.value = '';
    form.notes = '';
    errors.value = {};
};

// Dirty when the form no longer matches the secret it was populated from
const isDirty = computed(() => {
    const secret = props.secret;
    if (!secret) return false;
    return form.name !== secret.name || form.type !== (secret.type || 'other') || form.value !== secret.value || form.notes !== (secret.notes || '');
});

const forceClose = () => {
    emit('update:open', false);
    setTimeout(resetForm, 150);
};

// Intercept close attempts (Esc, overlay, cancel) to confirm discarding unsaved edits
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
    forceClose();
};

const confirmDiscard = () => {
    showDiscardConfirm.value = false;
    forceClose();
};

const submitForm = () => {
    if (isSubmitting.value || !form.id) return;

    isSubmitting.value = true;
    errors.value = {};

    const { id, ...formData } = form;

    router.put(route('secrets.update', id), formData, {
        preserveScroll: true,
        onSuccess: () => {
            forceClose();
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
        <SheetContent side="right" class="w-full gap-0 sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>Edit secret</SheetTitle>
                <SheetDescription> Update the secret below. Values are re-encrypted on save. </SheetDescription>
            </SheetHeader>

            <form id="edit-secret-form" @submit.prevent="submitForm" class="flex-1 space-y-5 overflow-y-auto px-4">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="edit-secret-name">
                        <span>Name<span aria-hidden="true" class="text-destructive">*</span></span>
                        <span class="sr-only">(required)</span>
                    </Label>
                    <Input
                        id="edit-secret-name"
                        v-model="form.name"
                        placeholder="e.g. Prod DB password"
                        :aria-invalid="errors.name ? true : undefined"
                        :aria-describedby="errors.name ? 'edit-secret-name-error' : undefined"
                    />
                    <InputError id="edit-secret-name-error" :message="errors.name" />
                </div>

                <!-- Type -->
                <div class="space-y-2">
                    <Label for="edit-secret-type">Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger id="edit-secret-type" class="w-full">
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
                    <Label for="edit-secret-value">
                        <span>Secret<span aria-hidden="true" class="text-destructive">*</span></span>
                        <span class="sr-only">(required)</span>
                    </Label>
                    <Textarea
                        id="edit-secret-value"
                        v-model="form.value"
                        rows="14"
                        spellcheck="false"
                        placeholder="Paste the password, token, full .env, or JSON blob…"
                        class="min-h-64 font-mono text-sm"
                        :aria-invalid="errors.value ? true : undefined"
                        :aria-describedby="errors.value ? 'edit-secret-value-error' : undefined"
                    />
                    <InputError id="edit-secret-value-error" :message="errors.value" />
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="edit-secret-notes">Notes</Label>
                    <Textarea
                        id="edit-secret-notes"
                        v-model="form.notes"
                        placeholder="Optional: host, port, username, URL…"
                        :aria-invalid="errors.notes ? true : undefined"
                        :aria-describedby="errors.notes ? 'edit-secret-notes-error' : undefined"
                    />
                    <InputError id="edit-secret-notes-error" :message="errors.notes" />
                </div>
            </form>

            <SheetFooter class="flex-col-reverse sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" :disabled="isSubmitting" @click="handleOpenChange(false)"> Cancel </Button>
                <Button type="submit" form="edit-secret-form" :disabled="isSubmitting">
                    <LoaderCircle v-if="isSubmitting" class="size-4 animate-spin" />
                    {{ isSubmitting ? 'Saving…' : 'Save changes' }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>

    <!-- Discard-changes confirmation -->
    <AlertDialog :open="showDiscardConfirm" @update:open="showDiscardConfirm = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Discard changes?</AlertDialogTitle>
                <AlertDialogDescription> You have unsaved edits. Closing now discards them. </AlertDialogDescription>
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
