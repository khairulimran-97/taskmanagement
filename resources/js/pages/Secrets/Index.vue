<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { BreadcrumbItem, Secret } from '@/types';
import { KeyRound, Eye, EyeOff, Copy, Check, Edit, Trash2, Search, ShieldCheck } from 'lucide-vue-next';
import { secretTypeBadge, secretTypeLabel } from '@/lib/secretMeta';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { toast } from 'vue-sonner';

import CreateSecretDialog from './Create.vue';
import EditSecretDialog from './Edit.vue';

interface Props {
    secrets: Secret[];
    search?: string;
}

const props = defineProps<Props>();

const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Vault', href: route('secrets.index') },
]);

// Modal states
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const editingSecret = ref<Secret | null>(null);
const isDeleteDialogOpen = ref(false);
const secretToDelete = ref<Secret | null>(null);

// Reveal / copy state (per row, keyed by id)
const revealed = ref<Set<number>>(new Set());
const copiedId = ref<number | null>(null);

// Search
const searchQuery = ref(props.search || '');
const searchTimeout = ref<number | null>(null);

watch(searchQuery, (value) => {
    if (searchTimeout.value) window.clearTimeout(searchTimeout.value);
    searchTimeout.value = window.setTimeout(() => {
        router.get(route('secrets.index'), { search: value || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const toggleReveal = (id: number) => {
    const next = new Set(revealed.value);
    next.has(id) ? next.delete(id) : next.add(id);
    revealed.value = next;
};

const isRevealed = (id: number) => revealed.value.has(id);

const isMultiline = (value: string) => value.includes('\n');

const maskOf = (value: string) => '•'.repeat(Math.min(Math.max(value.length, 6), 24));

const copyValue = async (secret: Secret) => {
    try {
        await navigator.clipboard.writeText(secret.value);
        copiedId.value = secret.id;
        toast.success('Secret copied to clipboard');
        window.setTimeout(() => {
            if (copiedId.value === secret.id) copiedId.value = null;
        }, 1500);
    } catch (e) {
        toast.error('Could not copy to clipboard');
    }
};

const openEditModal = (secret: Secret) => {
    editingSecret.value = secret;
    isEditModalOpen.value = true;
};

const handleEditModalClose = (isOpen: boolean) => {
    isEditModalOpen.value = isOpen;
    if (!isOpen) editingSecret.value = null;
};

const openDeleteDialog = (secret: Secret) => {
    secretToDelete.value = secret;
    isDeleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (secretToDelete.value) {
        router.delete(route('secrets.destroy', secretToDelete.value.id), {
            preserveScroll: true,
            onFinish: () => {
                isDeleteDialogOpen.value = false;
                secretToDelete.value = null;
            },
        });
    }
};

const cancelDelete = () => {
    isDeleteDialogOpen.value = false;
    secretToDelete.value = null;
};

const typeCount = computed(() => new Set(props.secrets.map((s) => s.type)).size);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Vault" />
        <PageContainer>
            <PageHeader title="Secret Vault" description="Store passwords, API tokens, and keys — encrypted at rest." :icon="KeyRound">
                <template #actions>
                    <CreateSecretDialog v-model:open="isCreateModalOpen" />
                </template>
            </PageHeader>

            <EditSecretDialog
                :open="isEditModalOpen"
                :secret="editingSecret"
                @update:open="handleEditModalClose"
            />

            <div class="mt-6 mb-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
                <StatCard :icon="KeyRound" label="Total Secrets" :value="props.secrets.length" accent />
                <StatCard :icon="ShieldCheck" label="Encrypted" :value="props.secrets.length" hint="at rest" />
                <StatCard :icon="KeyRound" label="Types" :value="typeCount" hint="categories" />
            </div>

            <!-- Search -->
            <div class="mb-4 relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input v-model="searchQuery" placeholder="Search by name or type…" class="pl-9" />
            </div>

            <div class="bg-card rounded-lg border border-border overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow class="bg-muted/50">
                            <TableHead class="font-semibold text-foreground">Name</TableHead>
                            <TableHead class="hidden sm:table-cell font-semibold text-foreground">Type</TableHead>
                            <TableHead class="font-semibold text-foreground">Secret</TableHead>
                            <TableHead class="hidden lg:table-cell font-semibold text-foreground">Notes</TableHead>
                            <TableHead class="text-right font-semibold text-foreground">Actions</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        <TableRow v-if="props.secrets.length === 0">
                            <TableCell :colspan="5" class="text-center py-12 text-muted-foreground">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-muted rounded-full flex items-center justify-center mb-4">
                                        <KeyRound class="w-8 h-8 text-muted-foreground/60" />
                                    </div>
                                    <span class="text-lg font-medium">No secrets yet</span>
                                    <span class="text-sm">Add your first secret to get started</span>
                                </div>
                            </TableCell>
                        </TableRow>

                        <TableRow
                            v-for="secret in props.secrets"
                            :key="secret.id"
                            class="group transition-colors hover:bg-muted/50 align-top"
                        >
                            <!-- Name (+ inline type on mobile) -->
                            <TableCell class="font-medium">
                                <span class="font-semibold text-foreground">{{ secret.name }}</span>
                                <div class="mt-1 sm:hidden">
                                    <Badge variant="outline" :class="secretTypeBadge(secret.type)" class="text-xs font-medium">
                                        {{ secretTypeLabel(secret.type) }}
                                    </Badge>
                                </div>
                            </TableCell>

                            <!-- Type -->
                            <TableCell class="hidden sm:table-cell">
                                <Badge variant="outline" :class="secretTypeBadge(secret.type)" class="font-medium">
                                    {{ secretTypeLabel(secret.type) }}
                                </Badge>
                            </TableCell>

                            <!-- Secret value -->
                            <TableCell class="max-w-xs">
                                <div class="flex items-start gap-1">
                                    <div class="min-w-0 flex-1">
                                        <pre
                                            v-if="isRevealed(secret.id)"
                                            class="font-mono text-xs whitespace-pre-wrap break-all max-h-48 overflow-y-auto rounded bg-muted/60 p-2 text-foreground"
                                        >{{ secret.value }}</pre>
                                        <span v-else class="font-mono text-sm tracking-widest text-muted-foreground select-none">
                                            {{ isMultiline(secret.value) ? '•••••• (multi-line)' : maskOf(secret.value) }}
                                        </span>
                                    </div>
                                    <div class="flex shrink-0 items-center">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-muted-foreground hover:text-primary"
                                            :title="isRevealed(secret.id) ? 'Hide' : 'Reveal'"
                                            @click="toggleReveal(secret.id)"
                                        >
                                            <EyeOff v-if="isRevealed(secret.id)" class="w-4 h-4" />
                                            <Eye v-else class="w-4 h-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-muted-foreground hover:text-primary"
                                            title="Copy"
                                            @click="copyValue(secret)"
                                        >
                                            <Check v-if="copiedId === secret.id" class="w-4 h-4 text-emerald-500" />
                                            <Copy v-else class="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>
                            </TableCell>

                            <!-- Notes -->
                            <TableCell class="hidden lg:table-cell max-w-xs">
                                <span class="text-muted-foreground line-clamp-2 whitespace-pre-wrap">
                                    {{ secret.notes || '—' }}
                                </span>
                            </TableCell>

                            <!-- Actions -->
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-0.5 sm:gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground transition-colors hover:text-primary"
                                        title="Edit"
                                        @click="openEditModal(secret)"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                        title="Delete"
                                        @click="openDeleteDialog(secret)"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Delete Confirmation Dialog -->
            <AlertDialog :open="isDeleteDialogOpen" @update:open="isDeleteDialogOpen = $event">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Secret</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{{ secretToDelete?.name }}"? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel @click="cancelDelete">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            @click="confirmDelete"
                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90 focus:ring-destructive"
                        >
                            Delete Secret
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </PageContainer>
    </AppLayout>
</template>
