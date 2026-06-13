<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { PlusCircle } from 'lucide-vue-next';
import { ref, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { secretTypes } from '@/lib/secretMeta';

interface Props {
    open: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'success': [];
}>();

const isSubmitting = ref(false);

const form = reactive({
    name: '',
    type: 'db_password',
    value: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});

const resetForm = () => {
    form.name = '';
    form.type = 'db_password';
    form.value = '';
    form.notes = '';
    errors.value = {};
};

const closeDialog = () => {
    emit('update:open', false);
};

const submitForm = () => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;
    errors.value = {};

    router.post(route('secrets.store'), form, {
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
        <SheetTrigger as-child>
            <Button variant="default" @click="resetForm">
                <PlusCircle class="w-4 h-4 mr-2" />
                <span>New Secret</span>
            </Button>
        </SheetTrigger>

        <SheetContent side="right" class="w-full gap-0 sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>New Secret</SheetTitle>
                <SheetDescription>
                    Store a password, API token, or any secret. Values are encrypted before they hit the database.
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="submitForm" class="flex-1 space-y-5 overflow-y-auto px-4">
                <!-- Name -->
                <div class="space-y-2">
                    <Label for="create-secret-name">Name *</Label>
                    <Input
                        id="create-secret-name"
                        v-model="form.name"
                        placeholder="e.g. Prod DB password"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">{{ errors.name }}</p>
                </div>

                <!-- Type -->
                <div class="space-y-2">
                    <Label for="create-secret-type">Type</Label>
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
                    <Label for="create-secret-value">Secret *</Label>
                    <Textarea
                        id="create-secret-value"
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
                    <Label for="create-secret-notes">Notes</Label>
                    <Textarea
                        id="create-secret-notes"
                        v-model="form.notes"
                        placeholder="Optional: host, port, username, URL…"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">{{ errors.notes }}</p>
                </div>
            </form>

            <SheetFooter>
                <Button type="button" @click="submitForm" :disabled="isSubmitting">
                    <span v-if="isSubmitting">Saving...</span>
                    <span v-else>Save Secret</span>
                </Button>
                <Button type="button" variant="outline" @click="closeDialog" :disabled="isSubmitting">
                    Cancel
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
