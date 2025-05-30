<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import { BreadcrumbItem, Note } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
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
    AlertCircle
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

// Check if content has changed
const contentChanged = computed(() => {
    return noteForm.value.content !== lastSavedContent.value ||
        noteForm.value.title !== lastSavedTitle.value;
});

// Auto-save status message
const autoSaveStatus = computed(() => {
    if (!isOnline.value) return { text: 'Offline', icon: CloudOff, class: 'text-orange-500' };
    if (autoSaveError.value) return { text: 'Save failed', icon: AlertCircle, class: 'text-red-500' };
    if (isAutoSaving.value) return { text: 'Saving...', icon: Loader2, class: 'text-blue-500' };
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

// Select a note
const selectNote = (note: Note) => {
    // Save current note if has unsaved changes
    if (hasUnsavedChanges.value) {
        autoSave();
    }

    router.get(route('notes.show', note.id), {}, {
        preserveState: true,
        preserveScroll: true
    });
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
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notes" />

        <div class="flex bg-background relative">
            <!-- Sidebar (Fixed/Sticky) -->
            <div class="w-80 border-r border-l border-b border-border bg-card h-screen sticky top-0 flex flex-col">
                <!-- Header -->
                <div class="p-4 border-b border-border shrink-0">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <FileText class="h-5 w-5 text-primary" />
                            <h1 class="text-lg font-semibold">All Notes</h1>
                        </div>
                        <Button @click="createNewNote" size="sm" class="h-8 w-8 p-0">
                            <Plus class="h-4 w-4" />
                        </Button>
                    </div>

                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search all notes and tags"
                            class="pl-9 h-9"
                        />
                    </div>
                </div>

                <!-- Notes List -->
                <ScrollArea class="flex-1 overflow-y-auto">
                    <div class="p-0">
                        <div v-if="!hasNotes" class="p-4 text-center text-muted-foreground">
                            <FileText class="h-12 w-12 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No notes found</p>
                            <Button @click="createNewNote" variant="outline" size="sm" class="mt-2">
                                Create your first note
                            </Button>
                        </div>

                        <div v-else class="">
                            <Card
                                v-for="note in filteredNotes"
                                :key="note.id"
                                @click="selectNote(note)"
                                class="cursor-pointer border-t border-b-0 border-r-0 border-l-0 rounded-none transition-colors hover:bg-accent/50 p-3"
                                :class="{ 'bg-accent': currentNote?.id === note.id }"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-medium text-sm truncate flex-1">
                                        {{ note.title || 'Untitled' }}
                                    </h3>
                                    <div class="flex items-center space-x-1 ml-2">
                                        <Button
                                            v-if="note.is_pinned"
                                            @click.stop="togglePin(note)"
                                            variant="ghost"
                                            size="icon"
                                            class="h-6 w-6 text-yellow-600"
                                        >
                                            <Pin class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <div class="flex items-center space-x-2">
                                        <Clock class="h-3 w-3" />
                                        <span>{{ formatDate(note.updated_at) }}</span>
                                    </div>
                                    <span v-if="note.word_count > 0">{{ note.word_count }} words</span>
                                </div>

                                <div v-if="note.tags.length > 0" class="flex flex-wrap gap-1 mt-2">
                                    <Badge
                                        v-for="tag in note.tags.slice(0, 3)"
                                        :key="tag"
                                        variant="secondary"
                                        class="text-xs px-1 py-0"
                                    >
                                        {{ tag }}
                                    </Badge>
                                    <Badge
                                        v-if="note.tags.length > 3"
                                        variant="secondary"
                                        class="text-xs px-1 py-0"
                                    >
                                        +{{ note.tags.length - 3 }}
                                    </Badge>
                                </div>
                            </Card>
                        </div>
                    </div>
                </ScrollArea>
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col">
                <div v-if="!hasSelectedNote" class="flex-1 flex items-center justify-center border-b border-r">
                    <div class="text-center text-muted-foreground">
                        <FileText class="h-24 w-24 mx-auto mb-4 opacity-30" />
                        <h3 class="text-lg font-medium mb-2">No note selected</h3>
                        <p class="text-sm mb-4">Choose a note from the sidebar to start reading, or create a new one.</p>
                        <Button @click="createNewNote">
                            <Plus class="h-4 w-4 mr-2" />
                            Create New Note
                        </Button>
                    </div>
                </div>

                <div v-else class="flex-1 flex flex-col">
                    <!-- Note Header -->
                    <div class="p-4 border-r border-border bg-card">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <Input
                                    v-if="isTitleEditing"
                                    ref="titleInput"
                                    v-model="noteForm.title"
                                    class="text-lg font-semibold border-none p-0 h-auto bg-transparent"
                                    placeholder="Untitled"
                                />
                                <h1
                                    v-else
                                    @click="toggleTitleEdit"
                                    class="text-lg font-semibold cursor-pointer hover:bg-accent/20 px-2 py-1 -mx-2 -my-1 rounded transition-colors"
                                >
                                    {{ currentNote.title || 'Untitled' }}
                                </h1>
                            </div>

                            <div class="flex items-center space-x-2">
                                <!-- Pin Button -->
                                <Button
                                    @click="togglePin(currentNote)"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    :class="{ 'text-yellow-600': currentNote.is_pinned }"
                                >
                                    <Pin v-if="currentNote.is_pinned" class="h-4 w-4" />
                                    <PinOff v-else class="h-4 w-4" />
                                </Button>

                                <!-- Save Button (shows state) -->
                                <Button
                                    @click="saveNote"
                                    size="sm"
                                    :disabled="isSaving || !contentChanged"
                                    :variant="hasUnsavedChanges ? 'default' : 'outline'"
                                    title="Save (Ctrl+S)"
                                >
                                    <Loader2 v-if="isSaving" class="h-4 w-4 mr-2 animate-spin" />
                                    <Save v-else class="h-4 w-4 mr-2" />
                                    {{ isSaving ? 'Saving...' : (contentChanged ? 'Save' : 'Saved') }}
                                </Button>

                                <!-- Title Edit Controls (only when editing title) -->
                                <template v-if="isTitleEditing">
                                    <Button
                                        @click="cancelTitleEdit"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Cancel
                                    </Button>
                                </template>

                                <!-- Delete Button -->
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive">
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
                                                Delete Note
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </div>

                        <!-- Meta Information with Auto-save Status -->
                        <div class="flex items-center justify-between text-xs text-muted-foreground mt-2">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-1">
                                    <Clock class="h-3 w-3" />
                                    <span>Updated {{ formatDate(currentNote.updated_at) }}</span>
                                </div>
                                <span v-if="currentNote.word_count > 0">{{ currentNote.word_count }} words</span>
                            </div>
                            <div class="flex items-center space-x-1" :class="autoSaveStatus.class">
                                <component
                                    :is="autoSaveStatus.icon"
                                    class="h-3 w-3"
                                    :class="{ 'animate-spin': autoSaveStatus.icon === Loader2 }"
                                />
                                <span>{{ autoSaveStatus.text }}</span>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div v-if="currentNote.tags.length > 0" class="flex flex-wrap gap-1 mt-2">
                            <Badge
                                v-for="tag in currentNote.tags"
                                :key="tag"
                                variant="secondary"
                                class="text-xs"
                            >
                                <Hash class="h-3 w-3 mr-1" />
                                {{ tag }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Note Content - Always Editable -->
                    <div class="flex-1 rounded-none border-l-0">
                        <TipTapEditor
                            v-model="noteForm.content"
                            :editable="true"
                            :noteId="currentNote?.id"
                            placeholder="Start writing your note... (Type '/' for commands)"
                            class="w-full h-full rounded-none"
                            @update:modelValue="(value) => noteForm.content = value"
                            @save="handleEditorSave"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
