<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import NoteItem from '@/components/notes/NoteItem.vue';
import { BreadcrumbItem, Note } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { toast } from 'vue-sonner'
import {
    Search,
    Plus,
    FileText,
    Pin,
    PinOff,
    Trash2,
    Clock,
    Hash,
    Save,
    Loader2,
    CheckCircle2,
    CloudOff,
    AlertCircle,
    ChevronLeft,
    AlignCenter,
    AlignJustify,
    Pencil,
} from 'lucide-vue-next';
import Image from '@/extensions/TipTapImageExtension';

interface Props {
    notes: Note[];
    selectedNote?: Note | null;
    search?: string;
}

const props = defineProps<Props>();
const page = usePage();

// Define breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Notes', href: route('notes.index') },
]);

// State
const searchQuery = ref(props.search || '');
const filteredNotes = ref<Note[]>(props.notes || []);
const currentNote = ref<Note | null>(props.selectedNote || null);
const isTitleEditing = ref(false);

// Reading width: 'full' (edge-to-edge) or 'centered' (~750px reading column)
const readingWidth = ref<'full' | 'centered'>(
    (typeof localStorage !== 'undefined' && (localStorage.getItem('notes_reading_width') as 'full' | 'centered')) || 'full',
);
const toggleReadingWidth = () => {
    readingWidth.value = readingWidth.value === 'full' ? 'centered' : 'full';
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('notes_reading_width', readingWidth.value);
    }
};
const isSaving = ref(false);
const isAutoSaving = ref(false);
const autoSaveTimeout = ref<number | null>(null);
const searchTimeout = ref<number | null>(null);
const lastSavedContent = ref('');
const lastSavedTitle = ref('');
const hasUnsavedChanges = ref(false);
const isOnline = ref(navigator.onLine);
const autoSaveError = ref(false);
const lastSaveTime = ref<Date | null>(null);
const saveRetryCount = ref(0);

// Form data for editing
const noteForm = ref({
    title: '',
    content: '',
    tags: [] as string[],
    is_pinned: false
});

// Refs for autofocus
const titleInput = ref<HTMLInputElement>();

// Computed
const hasNotes = computed(() => filteredNotes.value.length > 0);
const hasSelectedNote = computed(() => currentNote.value !== null);
const pinnedNotes = computed(() => filteredNotes.value.filter((n: any) => n.is_pinned));
const unpinnedNotes = computed(() => filteredNotes.value.filter((n: any) => !n.is_pinned));

// Check if content has changed
const contentChanged = computed(() => {
    return noteForm.value.content !== lastSavedContent.value ||
        noteForm.value.title !== lastSavedTitle.value;
});

// Auto-save status message
const autoSaveStatus = computed(() => {
    if (!isOnline.value) return { text: 'Offline', icon: CloudOff, class: 'text-orange-500' };
    if (autoSaveError.value) return { text: 'Save failed', icon: AlertCircle, class: 'text-red-500' };
    if (isAutoSaving.value) return { text: 'Saving...', icon: Loader2, class: 'text-primary' };
    if (hasUnsavedChanges.value) return { text: 'Unsaved changes', icon: AlertCircle, class: 'text-yellow-500' };
    if (lastSaveTime.value) {
        const timeSince = getTimeSince(lastSaveTime.value);
        return { text: `Saved ${timeSince}`, icon: CheckCircle2, class: 'text-green-500' };
    }
    return { text: 'All changes saved', icon: CheckCircle2, class: 'text-green-500' };
});

// Get time since last save
const getTimeSince = (date: Date): string => {
    const seconds = Math.floor((new Date().getTime() - date.getTime()) / 1000);
    if (seconds < 5) return 'just now';
    if (seconds < 60) return `${seconds}s ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    return `${hours}h ago`;
};

// Online/offline detection
onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Check online status periodically
    const onlineCheckInterval = setInterval(() => {
        isOnline.value = navigator.onLine;
    }, 5000);

    onUnmounted(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        clearInterval(onlineCheckInterval);
    });
});

const handleOnline = () => {
    isOnline.value = true;
    toast.success('Back online', {
        description: 'Your connection has been restored. Auto-save will resume.',
        duration: 3000,
    });
    // Immediately save any pending changes
    if (hasUnsavedChanges.value) {
        autoSave();
    }
};

const handleOffline = () => {
    isOnline.value = false;
    toast.error('You\'re offline', {
        description: 'Changes will be saved when you\'re back online.',
        duration: 5000,
    });
};

// Watch for flash messages and handle updates
watch(() => page.props.flash, (flash: any) => {
    if (flash) {
        // Handle auto-save success
        if (flash.auto_save_success && flash.updated_note) {
            const updatedNote = flash.updated_note;

            // Update current note
            if (currentNote.value && currentNote.value.id === updatedNote.id) {
                currentNote.value.updated_at = updatedNote.updated_at;
                currentNote.value.word_count = updatedNote.word_count;
            }

            // Update note in the list
            const noteIndex = filteredNotes.value.findIndex(n => n.id === updatedNote.id);
            if (noteIndex !== -1) {
                filteredNotes.value[noteIndex].updated_at = updatedNote.updated_at;
                filteredNotes.value[noteIndex].word_count = updatedNote.word_count;
            }

            // Reset save state
            lastSavedContent.value = noteForm.value.content;
            lastSavedTitle.value = noteForm.value.title;
            hasUnsavedChanges.value = false;
            autoSaveError.value = false;
            lastSaveTime.value = new Date();
            saveRetryCount.value = 0;
        }

        // Handle manual save success
        if (flash.success && flash.updated_note) {
            const updatedNote = flash.updated_note;

            // Update current note
            if (currentNote.value && currentNote.value.id === updatedNote.id) {
                Object.assign(currentNote.value, updatedNote);
                initializeForm();
            }

            // Update note in the list
            const noteIndex = filteredNotes.value.findIndex(n => n.id === updatedNote.id);
            if (noteIndex !== -1) {
                Object.assign(filteredNotes.value[noteIndex], updatedNote);
            }

            // Show success toast
            toast.success('Note saved', {
                description: 'Your note has been saved successfully.',
                duration: 2000,
            });
        }

        // Handle pin toggle
        if (flash.pin_updated) {
            const { id, is_pinned } = flash.pin_updated;

            // Update current note
            if (currentNote.value && currentNote.value.id === id) {
                currentNote.value.is_pinned = is_pinned;
                noteForm.value.is_pinned = is_pinned;
            }

            // Update note in the list
            const noteIndex = filteredNotes.value.findIndex(n => n.id === id);
            if (noteIndex !== -1) {
                filteredNotes.value[noteIndex].is_pinned = is_pinned;
            }
        }
    }
}, { deep: true });

// Initialize form when selectedNote changes
const initializeForm = () => {
    if (currentNote.value) {
        noteForm.value = {
            title: currentNote.value.title || 'Untitled',
            content: currentNote.value.content || '',
            tags: [...(currentNote.value.tags || [])],
            is_pinned: currentNote.value.is_pinned || false
        };
        lastSavedContent.value = noteForm.value.content;
        lastSavedTitle.value = noteForm.value.title;
        hasUnsavedChanges.value = false;
        autoSaveError.value = false;
        lastSaveTime.value = null;
        saveRetryCount.value = 0;
    }
};

// Watch for selectedNote changes
watch(() => props.selectedNote, (newNote) => {
    // Check for unsaved changes before switching
    if (currentNote.value && hasUnsavedChanges.value) {
        // Force a save before switching
        autoSave();
    }

    currentNote.value = newNote;
    initializeForm();
    isTitleEditing.value = false;
}, { immediate: true });

// Watch for notes changes
watch(() => props.notes, (newNotes) => {
    filteredNotes.value = newNotes;
}, { immediate: true });

// Search functionality
const performSearch = async () => {
    if (searchQuery.value.trim() === '') {
        filteredNotes.value = props.notes;
        return;
    }

    try {
        const url = new URL(route('notes.api.search'));
        url.searchParams.append('q', searchQuery.value);

        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        });

        if (response.ok) {
            const data = await response.json();
            if (data.success) {
                filteredNotes.value = data.notes;
            }
        }
    } catch (error) {
        console.error('Search failed:', error);
    }
};

// Debounced search
watch(searchQuery, () => {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = setTimeout(performSearch, 300);
});

// Select a note (client-side, no server roundtrip)
const selectNote = (note: Note) => {
    if (currentNote.value?.id === note.id) return;

    // Save current note if has unsaved changes
    if (hasUnsavedChanges.value) {
        autoSave();
    }

    currentNote.value = note;
    initializeForm();
    isTitleEditing.value = false;

    // Update URL without navigation
    window.history.replaceState({}, '', route('notes.show', note.id));
};

// Create new note
const createNewNote = () => {
    // Save current note if has unsaved changes
    if (hasUnsavedChanges.value) {
        autoSave();
    }

    router.post(route('notes.create-empty'), {}, {
        onSuccess: () => {
            // Note will be created and user redirected to it
        }
    });
};

// Toggle title edit mode
const toggleTitleEdit = async () => {
    if (!currentNote.value) return;

    isTitleEditing.value = !isTitleEditing.value;

    if (isTitleEditing.value) {
        await nextTick();
        setTimeout(() => {
            try {
                const titleEl = titleInput.value;
                if (titleEl) {
                    const inputElement = titleEl.$el?.querySelector('input') || titleEl;
                    if (inputElement && typeof inputElement.focus === 'function') {
                        inputElement.focus();
                    }
                }
            } catch (error) {
                console.debug('Title input focus not available');
            }
        }, 100);
    }
};

// Auto-save functionality using Inertia
const autoSave = async () => {
    if (!currentNote.value || isAutoSaving.value || !isOnline.value) return;
    if (!contentChanged.value) return;

    isAutoSaving.value = true;
    hasUnsavedChanges.value = true;

    router.put(route('notes.update', currentNote.value.id), {
        title: noteForm.value.title,
        content: noteForm.value.content,
        is_auto_save: true
    }, {
        preserveState: true,
        preserveScroll: true,
        only: [],
        onSuccess: () => {
            autoSaveError.value = false;
            saveRetryCount.value = 0;
        },
        onError: (errors) => {
            console.error('Auto-save failed:', errors);
            autoSaveError.value = true;
            saveRetryCount.value += 1;

            // Retry auto-save with exponential backoff
            if (saveRetryCount.value < 3) {
                const retryDelay = Math.pow(2, saveRetryCount.value) * 1000;
                setTimeout(autoSave, retryDelay);
            } else {
                toast.error('Auto-save failed', {
                    description: 'Unable to save your changes. Please try saving manually.',
                    duration: 5000,
                });
            }
        },
        onFinish: () => {
            isAutoSaving.value = false;
        }
    });
};

// Watch for form changes to trigger auto-save
watch([() => noteForm.value.title, () => noteForm.value.content], () => {
    if (!currentNote.value) return;

    hasUnsavedChanges.value = contentChanged.value;

    clearTimeout(autoSaveTimeout.value);
    autoSaveTimeout.value = setTimeout(autoSave, 5000);
});

// Manual save
const saveNote = async () => {
    if (!currentNote.value || !contentChanged.value) {
        toast.info('No changes to save', {
            description: 'Your note is already up to date.',
            duration: 2000,
        });
        return;
    }

    isSaving.value = true;

    router.put(route('notes.update', currentNote.value.id), {
        ...noteForm.value,
        is_auto_save: false
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isTitleEditing.value = false;
            lastSavedContent.value = noteForm.value.content;
            lastSavedTitle.value = noteForm.value.title;
            hasUnsavedChanges.value = false;
            autoSaveError.value = false;
            lastSaveTime.value = new Date();
            saveRetryCount.value = 0;
        },
        onError: (errors) => {
            console.error('Save failed:', errors);
            toast.error('Save failed', {
                description: 'Unable to save your note. Please try again.',
                duration: 3000,
            });
        },
        onFinish: () => {
            isSaving.value = false;
        }
    });
};

// Handle save from TipTap editor (Ctrl+S)
const handleEditorSave = () => {
    saveNote();
};

// Toggle pin using Inertia
const togglePin = async (note: Note) => {
    router.patch(route('notes.toggle-pin', note.id), {}, {
        preserveState: true,
        preserveScroll: true,
        only: [],
        onError: (errors) => {
            console.error('Toggle pin failed:', errors);
            toast.error('Failed to update pin status', {
                description: 'Please try again.',
                duration: 3000,
            });
        }
    });
};

// Delete note
const deleteNote = (noteId: number) => {
    router.delete(route('notes.destroy', noteId), {
        onSuccess: () => {
            toast.success('Note deleted', {
                description: 'Your note has been deleted successfully.',
                duration: 3000,
            });
        },
        onError: () => {
            console.error('Failed to delete note');
            toast.error('Failed to delete note', {
                description: 'Please try again.',
                duration: 3000,
            });
        }
    });
};

// Format date
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
        });
    }
};

// Cancel title editing
const cancelTitleEdit = () => {
    isTitleEditing.value = false;
    // Reset title to original value
    if (currentNote.value) {
        noteForm.value.title = currentNote.value.title || 'Untitled';
    }
};

// Handle before unload to warn about unsaved changes
onMounted(() => {
    initializeForm();

    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
        if (hasUnsavedChanges.value) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);

    onUnmounted(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);
        // Save any pending changes
        if (hasUnsavedChanges.value) {
            autoSave();
        }
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" full-bleed>
        <Head title="Notes" />

        <div class="notes-layout flex h-[calc(100vh-3.5rem)] w-full overflow-hidden">
            <!-- Sidebar -->
            <aside
                class="flex w-80 flex-col overflow-hidden border-r border-border bg-muted/20 xl:w-96"
                :class="{ 'hidden md:flex': hasSelectedNote, 'flex': !hasSelectedNote }"
            >
                <!-- Sidebar Header -->
                <div class="flex flex-col gap-3 border-b border-border p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-baseline gap-2">
                            <h1 class="font-display text-xl font-semibold tracking-tight text-foreground">Notes</h1>
                            <span class="text-sm text-muted-foreground">{{ filteredNotes.length }}</span>
                        </div>
                        <Button @click="createNewNote" size="sm" class="h-8 gap-1.5 px-3">
                            <Plus class="h-4 w-4" />
                            New
                        </Button>
                    </div>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search notes…"
                            class="h-9 rounded-lg pl-8 text-sm"
                        />
                    </div>
                </div>

                <!-- Notes List -->
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <!-- Empty State -->
                    <div v-if="!hasNotes" class="flex flex-col items-center justify-center px-6 py-16 text-center">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                            <FileText class="h-7 w-7 text-primary" />
                        </div>
                        <p class="font-display text-base font-semibold text-foreground">{{ searchQuery ? 'No matches' : 'No notes yet' }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ searchQuery ? 'Try a different search.' : 'Create your first note to get started.' }}
                        </p>
                        <Button v-if="!searchQuery" @click="createNewNote" variant="outline" size="sm" class="mt-4">
                            <Plus class="mr-1.5 h-4 w-4" />
                            Create note
                        </Button>
                    </div>

                    <!-- Note Items -->
                    <template v-else>
                        <!-- Pinned -->
                        <section v-if="pinnedNotes.length" class="bg-amber-500/[0.04]">
                            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-border/60 bg-muted/60 px-4 py-1.5 backdrop-blur-sm">
                                <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    <Pin class="h-3 w-3 text-amber-500" /> Pinned
                                </span>
                                <span class="text-[11px] font-medium text-muted-foreground/70">{{ pinnedNotes.length }}</span>
                            </div>
                            <NoteItem
                                v-for="note in pinnedNotes"
                                :key="note.id"
                                :note="note"
                                :active="currentNote?.id === note.id"
                                @select="selectNote(note)"
                            />
                        </section>

                        <!-- All -->
                        <section>
                            <div
                                v-if="pinnedNotes.length"
                                class="sticky top-0 z-10 flex items-center justify-between border-y border-border bg-muted/60 px-4 py-1.5 backdrop-blur-sm"
                            >
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">All notes</span>
                                <span class="text-[11px] font-medium text-muted-foreground/70">{{ unpinnedNotes.length }}</span>
                            </div>
                            <NoteItem
                                v-for="note in unpinnedNotes"
                                :key="note.id"
                                :note="note"
                                :active="currentNote?.id === note.id"
                                @select="selectNote(note)"
                            />
                        </section>
                    </template>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex flex-1 flex-col overflow-hidden"
                  :class="{ 'hidden md:flex': !hasSelectedNote }">

                <!-- No Note Selected -->
                <div v-if="!hasSelectedNote" class="flex flex-1 items-center justify-center bg-muted/10 p-6">
                    <div class="flex max-w-sm flex-col items-center text-center">
                        <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-3xl bg-primary/10">
                            <FileText class="h-9 w-9 text-primary" />
                        </div>
                        <h3 class="font-display text-2xl font-semibold tracking-tight text-foreground">Your notebook</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                            Select a note from the list, or start a fresh one. Everything autosaves as you write.
                        </p>
                        <Button @click="createNewNote" class="mt-5">
                            <Plus class="mr-2 h-4 w-4" />
                            New note
                        </Button>
                    </div>
                </div>

                <!-- Note Editor -->
                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <!-- Unified header -->
                    <header class="border-b border-border bg-card px-4 pb-3 pt-3.5 md:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Title + meta -->
                            <div class="flex min-w-0 flex-1 items-start gap-2">
                                <!-- Back button (mobile) -->
                                <Button
                                    @click="currentNote = null; router.get(route('notes.index'), {}, { preserveState: true })"
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-1 mt-0.5 h-8 w-8 shrink-0 p-0 md:hidden"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </Button>

                                <div class="min-w-0 flex-1">
                                    <!-- Title (editable) -->
                                    <Input
                                        v-if="isTitleEditing"
                                        ref="titleInput"
                                        v-model="noteForm.title"
                                        class="-mx-2 h-auto rounded-md border border-primary/40 bg-background px-2 py-0.5 font-display text-2xl font-semibold leading-tight tracking-tight ring-2 ring-primary/15 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/25"
                                        placeholder="Untitled"
                                        @blur="isTitleEditing = false"
                                        @keydown.enter="isTitleEditing = false"
                                        @keydown.escape="cancelTitleEdit"
                                    />
                                    <button
                                        v-else
                                        @click="toggleTitleEdit"
                                        class="group/title -mx-2 flex w-full items-center gap-2 rounded-md px-2 py-0.5 text-left transition-colors hover:bg-accent/40"
                                        title="Click to rename"
                                    >
                                        <span class="truncate font-display text-2xl font-semibold leading-tight tracking-tight text-foreground">
                                            {{ currentNote.title || 'Untitled' }}
                                        </span>
                                        <Pencil class="h-3.5 w-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/title:opacity-100" />
                                    </button>

                                    <!-- Inline meta -->
                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted-foreground">
                                        <span class="inline-flex items-center gap-1">
                                            <Clock class="h-3 w-3" />
                                            {{ formatDate(currentNote.updated_at) }}
                                        </span>
                                        <template v-if="currentNote.word_count > 0">
                                            <span class="text-muted-foreground/40">·</span>
                                            <span>{{ currentNote.word_count }} words</span>
                                        </template>
                                        <template v-if="currentNote.tags?.length > 0">
                                            <span class="text-muted-foreground/40">·</span>
                                            <span class="inline-flex items-center gap-1">
                                                <Badge
                                                    v-for="tag in currentNote.tags.slice(0, 3)"
                                                    :key="tag"
                                                    variant="secondary"
                                                    class="h-5 gap-1 px-1.5 text-[10px] font-normal"
                                                >
                                                    <Hash class="h-2.5 w-2.5" />{{ tag }}
                                                </Badge>
                                                <span v-if="currentNote.tags.length > 3" class="text-muted-foreground/70">+{{ currentNote.tags.length - 3 }}</span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex shrink-0 items-center gap-0.5">
                                <!-- Save status pill -->
                                <div
                                    class="mr-1 hidden h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-medium transition-colors sm:inline-flex"
                                    :class="hasUnsavedChanges
                                        ? 'bg-amber-500/12 text-amber-600 dark:text-amber-400'
                                        : 'bg-[hsl(150_24%_42%/0.12)] text-[hsl(150_30%_38%)] dark:text-[hsl(150_30%_55%)]'"
                                >
                                    <component
                                        :is="autoSaveStatus.icon"
                                        class="h-3.5 w-3.5"
                                        :class="{ 'animate-spin': autoSaveStatus.icon === Loader2 }"
                                    />
                                    <span>{{ autoSaveStatus.text }}</span>
                                </div>

                                <!-- Icon action group -->
                                <div class="flex h-9 items-center gap-0.5 rounded-lg border border-border bg-background/60 px-1">
                                    <Button
                                        @click="toggleReadingWidth"
                                        variant="ghost"
                                        size="sm"
                                        class="hidden h-7 w-7 p-0 text-muted-foreground hover:text-foreground md:inline-flex"
                                        :title="readingWidth === 'full' ? 'Centered reading column' : 'Full width'"
                                    >
                                        <AlignCenter v-if="readingWidth === 'full'" class="h-4 w-4" />
                                        <AlignJustify v-else class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        @click="togglePin(currentNote)"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 hover:text-amber-500"
                                        :class="currentNote.is_pinned ? 'text-amber-500' : 'text-muted-foreground'"
                                        :title="currentNote.is_pinned ? 'Unpin note' : 'Pin note'"
                                    >
                                        <Pin v-if="currentNote.is_pinned" class="h-4 w-4 fill-current" />
                                        <PinOff v-else class="h-4 w-4" />
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete note">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Delete Note</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Are you sure you want to delete "{{ currentNote.title || 'Untitled' }}"? This action cannot be undone.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                <AlertDialogAction
                                                    @click="deleteNote(currentNote.id)"
                                                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                >
                                                    Delete
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>

                                <!-- Save -->
                                <Button
                                    @click="saveNote"
                                    size="sm"
                                    :disabled="isSaving || !contentChanged"
                                    :variant="hasUnsavedChanges ? 'default' : 'outline'"
                                    class="ml-1 h-9 gap-1.5 px-3.5 text-xs"
                                    title="Save (Ctrl+S)"
                                >
                                    <Loader2 v-if="isSaving" class="h-3.5 w-3.5 animate-spin" />
                                    <Save v-else class="h-3.5 w-3.5" />
                                    {{ isSaving ? 'Saving…' : (contentChanged ? 'Save' : 'Saved') }}
                                </Button>
                            </div>
                        </div>
                    </header>

                    <!-- Editor -->
                    <div class="flex-1 overflow-y-auto">
                        <div
                            class="mx-auto h-full transition-all duration-300"
                            :class="readingWidth === 'centered' ? 'max-w-3xl' : 'max-w-none'"
                        >
                            <TipTapEditor
                                :key="currentNote?.id"
                                v-model="noteForm.content"
                                :editable="true"
                                :noteId="currentNote?.id"
                                placeholder="Start writing your note... (Type '/' for commands)"
                                class="h-full w-full !rounded-none !border-0"
                                @update:modelValue="(value) => noteForm.content = value"
                                @save="handleEditorSave"
                            />
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>

