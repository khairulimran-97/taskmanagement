<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/StatCard.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { secretTypeLabel } from '@/lib/secretMeta';
import { BreadcrumbItem, Secret } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, Copy, Edit, Eye, EyeOff, KeyRound, LoaderCircle, Search, StickyNote, Tags, Trash2, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
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
const isDeleting = ref(false);

// Reveal / copy state (per row, keyed by id)
const revealed = ref<Set<number>>(new Set());
const copiedId = ref<number | null>(null);

// Revealed values auto-mask after a while — this is a security surface
const REVEAL_AUTO_HIDE_MS = 30000;
const revealTimers = new Map<number, number>();

// Search
const searchQuery = ref(props.search || '');
const searchTimeout = ref<number | null>(null);
const isSearching = ref(false);
const hasSearch = computed(() => searchQuery.value.trim() !== '');

watch(searchQuery, (value) => {
    if (searchTimeout.value) window.clearTimeout(searchTimeout.value);
    searchTimeout.value = window.setTimeout(() => {
        isSearching.value = true;
        router.get(
            route('secrets.index'),
            { search: value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => {
                    isSearching.value = false;
                },
            },
        );
    }, 300);
});

const clearSearch = () => {
    searchQuery.value = '';
};

const hideSecret = (id: number) => {
    const next = new Set(revealed.value);
    next.delete(id);
    revealed.value = next;
    const timer = revealTimers.get(id);
    if (timer) {
        window.clearTimeout(timer);
        revealTimers.delete(id);
    }
};

const toggleReveal = (id: number) => {
    if (revealed.value.has(id)) {
        hideSecret(id);
        return;
    }
    const next = new Set(revealed.value);
    next.add(id);
    revealed.value = next;
    revealTimers.set(
        id,
        window.setTimeout(() => hideSecret(id), REVEAL_AUTO_HIDE_MS),
    );
};

onUnmounted(() => {
    revealTimers.forEach((timer) => window.clearTimeout(timer));
    revealTimers.clear();
    if (searchTimeout.value) window.clearTimeout(searchTimeout.value);
});

const isRevealed = (id: number) => revealed.value.has(id);

const isMultiline = (value: string) => value.includes('\n');

const maskOf = (value: string) => '•'.repeat(Math.min(Math.max(value.length, 6), 24));

const copyValue = async (secret: Secret) => {
    try {
        await navigator.clipboard.writeText(secret.value);
        copiedId.value = secret.id;
        toast.success('Copied');
        window.setTimeout(() => {
            if (copiedId.value === secret.id) copiedId.value = null;
        }, 1500);
    } catch {
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

const handleDeleteDialogOpen = (value: boolean) => {
    if (isDeleting.value) return;
    // Only toggle the flag here — confirmDelete may still need secretToDelete
    // if the dialog auto-closes before the action's click handler runs
    isDeleteDialogOpen.value = value;
};

const confirmDelete = () => {
    if (!secretToDelete.value || isDeleting.value) return;
    isDeleting.value = true;
    router.delete(route('secrets.destroy', secretToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            isDeleteDialogOpen.value = false;
            secretToDelete.value = null;
        },
    });
};

const cancelDelete = () => {
    if (isDeleting.value) return;
    isDeleteDialogOpen.value = false;
    secretToDelete.value = null;
};

const typeCount = computed(() => new Set(props.secrets.map((s) => s.type)).size);
const withNotesCount = computed(() => props.secrets.filter((s) => (s.notes || '').trim() !== '').length);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Vault" />
        <PageContainer>
            <PageHeader title="Secret vault" description="Store passwords, API tokens, and keys — encrypted at rest." :icon="KeyRound">
                <template #actions>
                    <CreateSecretDialog v-model:open="isCreateModalOpen" />
                </template>
            </PageHeader>

            <EditSecretDialog :open="isEditModalOpen" :secret="editingSecret" @update:open="handleEditModalClose" />

            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
                <StatCard :icon="KeyRound" label="Total secrets" :value="props.secrets.length" hint="Encrypted at rest" accent />
                <StatCard :icon="Tags" label="Types" :value="typeCount" />
                <StatCard :icon="StickyNote" label="With notes" :value="withNotesCount" />
            </div>

            <!-- Search -->
            <div class="relative mb-4 max-w-sm">
                <LoaderCircle v-if="isSearching" class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2 animate-spin" />
                <Search v-else class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input v-model="searchQuery" placeholder="Search by name or type…" class="px-9" />
                <button
                    v-if="hasSearch"
                    type="button"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 absolute top-1/2 right-2 flex size-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                    aria-label="Clear search"
                    @click="clearSearch"
                >
                    <X class="size-3.5" />
                </button>
            </div>

            <EmptyState
                v-if="props.secrets.length === 0 && hasSearch"
                :icon="Search"
                title="No matching secrets"
                description="Nothing matches your search. Try a different name or type."
            >
                <template #action>
                    <Button variant="outline" @click="clearSearch">Clear search</Button>
                </template>
            </EmptyState>

            <EmptyState
                v-else-if="props.secrets.length === 0"
                :icon="KeyRound"
                title="No secrets yet"
                description="Keep passwords, tokens, and keys in one encrypted place."
            >
                <template #action>
                    <Button @click="isCreateModalOpen = true">New secret</Button>
                </template>
            </EmptyState>

            <TooltipProvider v-else :delay-duration="300">
                <div class="border-border bg-card overflow-x-auto rounded-lg border shadow-xs">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-muted/50">
                                <TableHead>Name</TableHead>
                                <TableHead class="hidden sm:table-cell">Type</TableHead>
                                <TableHead>Secret</TableHead>
                                <TableHead class="hidden lg:table-cell">Notes</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            <TableRow
                                v-for="secret in props.secrets"
                                :key="secret.id"
                                class="group hover:bg-muted/50 align-top transition-colors duration-150"
                            >
                                <!-- Name (+ inline type / notes on smaller screens) -->
                                <TableCell class="font-medium">
                                    <span class="text-foreground font-medium">{{ secret.name }}</span>
                                    <div class="mt-1 sm:hidden">
                                        <Badge variant="outline" class="text-muted-foreground font-normal">
                                            {{ secretTypeLabel(secret.type) }}
                                        </Badge>
                                    </div>
                                    <p v-if="secret.notes" class="text-muted-foreground mt-1 line-clamp-1 max-w-48 text-xs lg:hidden">
                                        {{ secret.notes }}
                                    </p>
                                </TableCell>

                                <!-- Type -->
                                <TableCell class="hidden sm:table-cell">
                                    <Badge variant="outline" class="text-muted-foreground font-normal">
                                        {{ secretTypeLabel(secret.type) }}
                                    </Badge>
                                </TableCell>

                                <!-- Secret value -->
                                <TableCell class="max-w-xs">
                                    <div class="flex items-start gap-1">
                                        <div class="min-w-0 flex-1">
                                            <Transition name="fade" mode="out-in">
                                                <pre
                                                    v-if="isRevealed(secret.id)"
                                                    class="bg-muted/60 text-foreground max-h-48 overflow-y-auto rounded-md p-2 font-mono text-xs break-all whitespace-pre-wrap"
                                                    >{{ secret.value }}</pre
                                                >
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 cursor-pointer rounded-md text-left font-mono text-sm tracking-widest transition-colors duration-150 select-none focus-visible:ring-2 focus-visible:outline-none"
                                                    aria-label="Reveal value"
                                                    @click="toggleReveal(secret.id)"
                                                >
                                                    {{ isMultiline(secret.value) ? '•••••• (multi-line)' : maskOf(secret.value) }}
                                                </button>
                                            </Transition>
                                        </div>
                                        <div class="flex shrink-0 items-center">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="text-muted-foreground hover:text-foreground size-8"
                                                        :aria-label="isRevealed(secret.id) ? 'Hide value' : 'Reveal value'"
                                                        :aria-pressed="isRevealed(secret.id)"
                                                        @click="toggleReveal(secret.id)"
                                                    >
                                                        <EyeOff v-if="isRevealed(secret.id)" class="size-4" />
                                                        <Eye v-else class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>{{ isRevealed(secret.id) ? 'Hide value' : 'Reveal value' }}</TooltipContent>
                                            </Tooltip>
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        class="text-muted-foreground hover:text-foreground size-8"
                                                        aria-label="Copy value"
                                                        @click="copyValue(secret)"
                                                    >
                                                        <Check v-if="copiedId === secret.id" class="text-success size-4" />
                                                        <Copy v-else class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>Copy value</TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </div>
                                </TableCell>

                                <!-- Notes -->
                                <TableCell class="hidden max-w-xs lg:table-cell">
                                    <span class="text-muted-foreground line-clamp-2 whitespace-pre-wrap">
                                        {{ secret.notes || '—' }}
                                    </span>
                                </TableCell>

                                <!-- Actions -->
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-0.5 sm:gap-1">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="text-muted-foreground hover:text-foreground size-8"
                                                    :aria-label="`Edit ${secret.name}`"
                                                    @click="openEditModal(secret)"
                                                >
                                                    <Edit class="size-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Edit</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive size-8"
                                                    :aria-label="`Delete ${secret.name}`"
                                                    @click="openDeleteDialog(secret)"
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>Delete</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </TooltipProvider>

            <!-- Delete confirmation -->
            <AlertDialog :open="isDeleteDialogOpen" @update:open="handleDeleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete secret</AlertDialogTitle>
                        <AlertDialogDescription>
                            This permanently deletes "{{ secretToDelete?.name }}". This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel :disabled="isDeleting" @click="cancelDelete">Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            class="bg-destructive hover:bg-destructive/90 focus-visible:ring-destructive/30 text-white"
                            :disabled="isDeleting"
                            @click="confirmDelete"
                        >
                            <LoaderCircle v-if="isDeleting" class="size-4 animate-spin" />
                            {{ isDeleting ? 'Deleting…' : 'Delete secret' }}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </PageContainer>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
