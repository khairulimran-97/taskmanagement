<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, reactive, watch } from 'vue';
import { Secret } from '@/types';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { secretTypes } from '@/lib/secretMeta';

interface Props {
    open: boolean;
    secret: Secret | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const isSubmitting = ref(false);

const form = reactive({
    id: null as number | null,
    name: '',
    type: 'db_password',
    value: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});

watch([() => props.open, () => props.secret], ([isOpen, secret]) => {
    if (isOpen && secret) {
        populateForm(secret);
    }
}, { immediate: true });

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

const closeDialog = () => {
    emit('update:open', false);
    setTimeout(resetForm, 150);
};

const submitForm = () => {
    if (isSubmitting.value || !form.id) return;

    isSubmitting.value = true;
    errors.value = {};

    const { id, ...formData } = form;

    router.put(route('secrets.update', id), formData, {
        preserveScroll: true,
        onSuccess: () => {
            closeDialog();
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
    <Sheet :open="open" @update:open="$emit('update:open', $event)">
        <SheetContent side="right" class="w-full gap-0 sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>Edit Secret</SheetTitle>
                <SheetDescription>
                    Update the secret below. Values are re-encrypted on save.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="submitForm" class="flex-1 space-y-5 overflow-y-auto px-4">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="edit-secret-name">Name *</Label>
                    <Input
                        id="edit-secret-name"
                        v-model="form.name"
                        placeholder="e.g. Prod DB password"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">{{ errors.name }}</p>
                </div>

                <!-- Type -->
                <div class="space-y-2">
                    <Label for="edit-secret-type">Type</Label>
                    <Select v-model="form.type">
                        <SelectTrigger class="w-full">
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
                    <Label for="edit-secret-value">Secret *</Label>
                    <Textarea
                        id="edit-secret-value"
                        v-model="form.value"
                        rows="14"
                        spellcheck="false"
                        placeholder="Paste the password, token, full .env, or JSON blob…"
                        class="min-h-[16rem] font-mono text-sm"
                        :class="{ 'border-red-500': errors.value }"
                    />
                    <p v-if="errors.value" class="text-sm text-red-500">{{ errors.value }}</p>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="edit-secret-notes">Notes</Label>
                    <Textarea
                        id="edit-secret-notes"
                        v-model="form.notes"
                        placeholder="Optional: host, port, username, URL…"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">{{ errors.notes }}</p>
                </div>
            </form>

            <SheetFooter>
                <Button type="button" @click="submitForm" :disabled="isSubmitting">
                    <span v-if="isSubmitting">Updating...</span>
                    <span v-else>Update Secret</span>
                </Button>
                <Button type="button" variant="outline" @click="closeDialog" :disabled="isSubmitting">
                    Cancel
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
