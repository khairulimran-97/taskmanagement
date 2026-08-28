<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import NoteItem from '@/components/notes/NoteItem.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, Note } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlignCenter,
    AlignJustify,
    CheckCircle2,
    ChevronLeft,
    Clock,
    CloudOff,
    FileText,
    Hash,
    Loader2,
    Pencil,
    Pin,
    PinOff,
    Plus,
    Save,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    notes: Note[];
    selectedNote?: Note | null;
    search?: string;
}

const props = defineProps<Props>();
const page = usePage();

// No breadcrumb on Notes — it's a full-width workspace
const breadcrumbs = ref<BreadcrumbItem[]>([]);

// State
const searchQuery = ref(props.search || '');
const filteredNotes = ref<Note[]>(props.notes || []);
const currentNote = ref<Note | null>(props.selectedNote || null);
const isTitleEditing = ref(false);
// Mobile is single-pane: start on the list even if the server pre-selected a note
const mobileShowEditor = ref(false);

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
    is_pinned: false,
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
    return noteForm.value.content !== lastSavedContent.value || noteForm.value.title !== lastSavedTitle.value;
});

// Auto-save status message — status colors follow the app's semantic token language
const autoSaveStatus = computed(() => {
    if (!isOnline.value) return { text: 'Offline', icon: CloudOff, pill: 'bg-warning/10 text-warning' };
    if (autoSaveError.value) return { text: 'Save failed', icon: AlertCircle, pill: 'bg-destructive/10 text-destructive' };
    if (isAutoSaving.value) return { text: 'Saving…', icon: Loader2, pill: 'bg-primary/10 text-primary' };
    if (hasUnsavedChanges.value) return { text: 'Unsaved changes', icon: AlertCircle, pill: 'bg-warning/10 text-warning' };
    if (lastSaveTime.value) {
        const timeSince = getTimeSince(lastSaveTime.value);
        return { text: `Saved ${timeSince}`, icon: CheckCircle2, pill: 'bg-success/10 text-success' };
    }
    return { text: 'All changes saved', icon: CheckCircle2, pill: 'bg-success/10 text-success' };
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
    toast.error("You're offline", {
        description: "Changes will be saved when you're back online.",
        duration: 5000,
    });
};

// Watch for flash messages and handle updates
watch(
    () => page.props.flash,
    (flash: any) => {
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
                const noteIndex = filteredNotes.value.findIndex((n) => n.id === updatedNote.id);
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
                const noteIndex = filteredNotes.value.findIndex((n) => n.id === updatedNote.id);
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
                const noteIndex = filteredNotes.value.findIndex((n) => n.id === id);
                if (noteIndex !== -1) {
                    filteredNotes.value[noteIndex].is_pinned = is_pinned;
                }
            }
        }
    },
    { deep: true },
);

// Initialize form when selectedNote changes
const initializeForm = () => {
    if (currentNote.value) {
        noteForm.value = {
            title: currentNote.value.title || 'Untitled',
            content: currentNote.value.content || '',
            tags: [...(currentNote.value.tags || [])],
            is_pinned: currentNote.value.is_pinned || false,
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
watch(
    () => props.selectedNote,
    (newNote) => {
        // Check for unsaved changes before switching
        if (currentNote.value && hasUnsavedChanges.value) {
            // Force a save before switching
            autoSave();
        }

        currentNote.value = newNote;
        initializeForm();
        isTitleEditing.value = false;
    },
    { immediate: true },
);

// Watch for notes changes
watch(
    () => props.notes,
    (newNotes) => {
        filteredNotes.value = newNotes;
    },
    { immediate: true },
);

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
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
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
    mobileShowEditor.value = true;
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

// Create new note — guarded so double-clicks can't create two notes
const isCreatingNote = ref(false);
const createNewNote = () => {
    if (isCreatingNote.value) return;

    // Save current note if has unsaved changes
    if (hasUnsavedChanges.value) {
        autoSave();
    }

    isCreatingNote.value = true;
    router.post(
        route('notes.create-empty'),
        {},
        {
            onSuccess: () => {
                toast.success('Note created', { duration: 2000 });
            },
            onFinish: () => {
                isCreatingNote.value = false;
            },
        },
    );
};

// Toggle title edit mode
const toggleTitleEdit = async () => {
    if (!currentNote.value) return;

    isTitleEditing.value = !isTitleEditing.value;

    if (isTitleEditing.value) {
        await nextTick();
        const el = titleInput.value as HTMLInputElement | null;
        if (el) {
            el.focus();
            el.select();
        }
    }
};

// Auto-save functionality using Inertia
const autoSave = async () => {
    if (!currentNote.value || isAutoSaving.value || !isOnline.value) return;
    if (!contentChanged.value) return;

    isAutoSaving.value = true;
    hasUnsavedChanges.value = true;

    router.put(
        route('notes.update', currentNote.value.id),
        {
            title: noteForm.value.title,
            content: noteForm.value.content,
            is_auto_save: true,
        },
        {
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
            },
        },
    );
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

    router.put(
        route('notes.update', currentNote.value.id),
        {
            ...noteForm.value,
            is_auto_save: false,
        },
        {
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
            },
        },
    );
};

// Handle save from TipTap editor (Ctrl+S)
const handleEditorSave = () => {
    saveNote();
};

// Toggle pin using Inertia — disabled while the request is in flight
const isTogglingPin = ref(false);
const togglePin = async (note: Note) => {
    if (isTogglingPin.value) return;
    isTogglingPin.value = true;
    // Capture pre-toggle state: the flash watcher may flip note.is_pinned before onSuccess runs
    const wasPinned = note.is_pinned;

    router.patch(
        route('notes.toggle-pin', note.id),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: [],
            onSuccess: () => {
                toast.success(wasPinned ? 'Note unpinned' : 'Note pinned', { duration: 2000 });
            },
            onError: (errors) => {
                console.error('Toggle pin failed:', errors);
                toast.error('Failed to update pin status', {
                    description: 'Please try again.',
                    duration: 3000,
                });
            },
            onFinish: () => {
                isTogglingPin.value = false;
            },
        },
    );
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
        },
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
            hour12: true,
        });
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        return `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
        });
    }
};

// Commit title edit (Enter / explicit save)
const commitTitleEdit = () => {
    isTitleEditing.value = false;
};

// Cancel title editing — restore original (Esc / click away)
const cancelTitleEdit = () => {
    if (currentNote.value) {
        noteForm.value.title = currentNote.value.title || 'Untitled';
    }
    isTitleEditing.value = false;
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
                class="border-border bg-muted/20 w-full flex-col overflow-hidden border-r md:flex md:w-80 xl:w-96"
                :class="mobileShowEditor ? 'hidden md:flex' : 'flex'"
            >
                <!-- Sidebar Header -->
                <div class="border-border bg-card/40 flex flex-col gap-3 border-b px-4 pt-5 pb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <h1 class="text-foreground text-xl font-semibold tracking-tight">Notes</h1>
                            <span
                                class="bg-muted text-muted-foreground inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1.5 text-xs font-semibold tabular-nums"
                            >
                                {{ filteredNotes.length }}
                            </span>
                        </div>
                        <Button @click="createNewNote" size="sm" class="h-9 gap-1.5 px-3.5" :disabled="isCreatingNote">
                            <Loader2 v-if="isCreatingNote" class="size-4 animate-spin" />
                            <Plus v-else class="size-4" />
                            New note
                        </Button>
                    </div>
                    <div class="group relative">
                        <Search
                            class="text-muted-foreground group-focus-within:text-primary pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 transition-colors duration-150"
                        />
                        <Input v-model="searchQuery" placeholder="Search notes…" class="border-border bg-background h-10 pr-9 pl-9 text-sm" />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            type="button"
                            class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/50 absolute top-1/2 right-2.5 flex size-6 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                            aria-label="Clear search"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Notes List -->
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <!-- Empty State -->
                    <div v-if="!hasNotes" class="p-4">
                        <EmptyState
                            :icon="searchQuery ? Search : FileText"
                            :title="searchQuery ? 'No matches' : 'No notes yet'"
                            :description="searchQuery ? 'Try a different search.' : 'Create your first note to get started.'"
                        >
                            <template v-if="!searchQuery" #action>
                                <Button @click="createNewNote" variant="outline" size="sm" :disabled="isCreatingNote">
                                    <Loader2 v-if="isCreatingNote" class="mr-1.5 size-4 animate-spin" />
                                    <Plus v-else class="mr-1.5 size-4" />
                                    New note
                                </Button>
                            </template>
                        </EmptyState>
                    </div>

                    <!-- Note Items -->
                    <template v-else>
                        <!-- Pinned -->
                        <section v-if="pinnedNotes.length">
                            <div class="border-border/60 bg-muted sticky top-0 z-10 flex items-center justify-between border-b px-4 py-1.5">
                                <span class="text-muted-foreground flex items-center gap-1.5 text-[11px] font-medium tracking-wider uppercase">
                                    <Pin class="size-3" /> Pinned
                                </span>
                                <span class="text-muted-foreground/70 text-[11px] font-medium tabular-nums">{{ pinnedNotes.length }}</span>
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
                                class="border-border bg-muted sticky top-0 z-10 flex items-center justify-between border-y px-4 py-1.5"
                            >
                                <span class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase">All notes</span>
                                <span class="text-muted-foreground/70 text-[11px] font-medium tabular-nums">{{ unpinnedNotes.length }}</span>
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
            <main class="flex-1 flex-col overflow-hidden md:flex" :class="mobileShowEditor ? 'flex' : 'hidden md:flex'">
                <!-- No Note Selected -->
                <div v-if="!hasSelectedNote" class="bg-muted/10 flex flex-1 items-center justify-center p-6">
                    <div class="w-full max-w-sm">
                        <EmptyState
                            :icon="FileText"
                            title="Your notebook"
                            description="Select a note from the list, or start a fresh one. Everything autosaves as you write."
                        >
                            <template #action>
                                <Button @click="createNewNote" :disabled="isCreatingNote">
                                    <Loader2 v-if="isCreatingNote" class="mr-2 size-4 animate-spin" />
                                    <Plus v-else class="mr-2 size-4" />
                                    New note
                                </Button>
                            </template>
                        </EmptyState>
                    </div>
                </div>

                <!-- Note Editor -->
                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <!-- Unified header -->
                    <header class="border-border bg-card border-b px-4 pt-3.5 pb-3 md:px-6">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Title + meta -->
                            <div class="flex min-w-0 flex-1 items-start gap-2">
                                <!-- Back button (mobile) -->
                                <Button
                                    @click="mobileShowEditor = false"
                                    variant="ghost"
                                    size="sm"
                                    class="mt-0.5 -ml-1 size-8 shrink-0 p-0 md:hidden"
                                    aria-label="Back to notes list"
                                >
                                    <ChevronLeft class="size-4" />
                                </Button>

                                <div class="min-w-0 flex-1">
                                    <!-- Title (editable) -->
                                    <!-- Blur commits the edit (Esc cancels) so clicking away doesn't silently discard a typed title -->
                                    <input
                                        v-if="isTitleEditing"
                                        ref="titleInput"
                                        v-model="noteForm.title"
                                        type="text"
                                        class="border-input bg-background text-foreground focus-visible:ring-ring/50 -mx-2 w-full rounded-md border px-2 py-0.5 text-xl leading-tight font-semibold tracking-tight outline-none focus-visible:ring-2"
                                        placeholder="Untitled"
                                        @blur="isTitleEditing && commitTitleEdit()"
                                        @keydown.enter.prevent="commitTitleEdit"
                                        @keydown.esc.prevent="cancelTitleEdit"
                                    />
                                    <button
                                        v-else
                                        @click="toggleTitleEdit"
                                        type="button"
                                        class="group/title hover:bg-muted/60 focus-visible:ring-ring/50 -mx-2 flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-0.5 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                        title="Rename note"
                                        aria-label="Rename note"
                                    >
                                        <span class="text-foreground truncate text-xl leading-tight font-semibold tracking-tight">
                                            {{ currentNote.title || 'Untitled' }}
                                        </span>
                                        <Pencil
                                            class="text-muted-foreground size-3.5 shrink-0 opacity-0 transition-opacity duration-150 group-hover/title:opacity-100 group-focus-visible/title:opacity-100"
                                        />
                                    </button>

                                    <!-- Inline meta -->
                                    <div class="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs">
                                        <span class="inline-flex items-center gap-1 tabular-nums">
                                            <Clock class="size-3" />
                                            {{ formatDate(currentNote.updated_at) }}
                                        </span>
                                        <template v-if="currentNote.word_count > 0">
                                            <span class="text-muted-foreground/40">·</span>
                                            <span class="tabular-nums">{{ currentNote.word_count }} words</span>
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
                                                <span v-if="currentNote.tags.length > 3" class="text-muted-foreground/70"
                                                    >+{{ currentNote.tags.length - 3 }}</span
                                                >
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex shrink-0 items-center gap-1">
                                <!-- Save status pill (icon-only on mobile so phones still get autosave feedback) -->
                                <div
                                    class="mr-1 inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors duration-150"
                                    :class="autoSaveStatus.pill"
                                >
                                    <component
                                        :is="autoSaveStatus.icon"
                                        class="size-3.5"
                                        :class="{ 'animate-spin': autoSaveStatus.icon === Loader2 }"
                                    />
                                    <span class="hidden sm:inline">{{ autoSaveStatus.text }}</span>
                                </div>

                                <!-- Icon action group -->
                                <div class="border-border bg-background/60 flex h-9 items-center gap-0.5 rounded-md border px-0.5">
                                    <Button
                                        @click="toggleReadingWidth"
                                        variant="ghost"
                                        size="sm"
                                        class="text-muted-foreground hover:text-foreground hidden size-8 p-0 md:inline-flex"
                                        :title="readingWidth === 'full' ? 'Centered reading column' : 'Full width'"
                                        :aria-label="readingWidth === 'full' ? 'Switch to centered reading column' : 'Switch to full width'"
                                    >
                                        <AlignCenter v-if="readingWidth === 'full'" class="size-4" />
                                        <AlignJustify v-else class="size-4" />
                                    </Button>
                                    <Button
                                        @click="togglePin(currentNote)"
                                        variant="ghost"
                                        size="sm"
                                        class="size-8 p-0"
                                        :class="
                                            currentNote.is_pinned ? 'text-primary hover:text-primary' : 'text-muted-foreground hover:text-foreground'
                                        "
                                        :disabled="isTogglingPin"
                                        :title="currentNote.is_pinned ? 'Unpin note' : 'Pin note'"
                                        :aria-label="currentNote.is_pinned ? 'Unpin note' : 'Pin note'"
                                    >
                                        <Pin v-if="currentNote.is_pinned" class="size-4 fill-current" />
                                        <PinOff v-else class="size-4" />
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive size-8 p-0"
                                                title="Delete note"
                                                aria-label="Delete note"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Delete note</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    "{{ currentNote.title || 'Untitled' }}" will be permanently deleted. This cannot be undone.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                <AlertDialogAction
                                                    @click="deleteNote(currentNote.id)"
                                                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                >
                                                    Delete note
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>

                                <!-- Save (status lives in the pill; the button only states its action) -->
                                <Button
                                    @click="saveNote"
                                    size="sm"
                                    :disabled="isSaving || !contentChanged"
                                    :variant="hasUnsavedChanges ? 'default' : 'outline'"
                                    class="ml-1 h-9 gap-1.5 px-3.5 text-xs"
                                    title="Save (Ctrl+S)"
                                >
                                    <Loader2 v-if="isSaving" class="size-3.5 animate-spin" />
                                    <Save v-else class="size-3.5" />
                                    {{ isSaving ? 'Saving…' : 'Save' }}
                                </Button>
                            </div>
                        </div>
                    </header>

                    <!-- Editor -->
                    <div class="flex-1 overflow-y-auto">
                        <div class="mx-auto h-full transition-all duration-200" :class="readingWidth === 'centered' ? 'max-w-3xl' : 'max-w-none'">
                            <TipTapEditor
                                :key="currentNote?.id"
                                v-model="noteForm.content"
                                :editable="true"
                                :noteId="currentNote?.id"
                                placeholder="Start writing your note... (Type '/' for commands)"
                                class="h-full w-full !rounded-none !border-0"
                                @update:modelValue="(value) => (noteForm.content = value)"
                                @save="handleEditorSave"
                            />
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>
</template>
