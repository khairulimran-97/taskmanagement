<script setup lang="ts">
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import TipTapEditor from '@/components/TipTapEditor.vue';
import { BreadcrumbItem, Note } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import {
    Search,
    Plus,
    FileText,
    Pin,
    PinOff,
    Trash2,
    Clock,
    Edit3,
    Hash
} from 'lucide-vue-next';

interface Props {
    notes: Note[];
    selectedNote?: Note | null;
    search?: string;
}

const props = defineProps<Props>();

// Define breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Notes', href: route('notes.index') },
]);

// State
const searchQuery = ref(props.search || '');
const filteredNotes = ref<Note[]>(props.notes || []);
const currentNote = ref<Note | null>(props.selectedNote || null);
const isEditing = ref(false);
const isSaving = ref(false);
const autoSaveTimeout = ref<number | null>(null);

// Form data for editing
const noteForm = ref({
    title: '',
    content: '',
    tags: [] as string[],
    is_pinned: false
});

// Refs for auto-focus
const titleInput = ref<HTMLInputElement>();

// Helper function to strip HTML tags for preview
const stripHtml = (html: string): string => {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
};

// Computed
const hasNotes = computed(() => filteredNotes.value.length > 0);
const hasSelectedNote = computed(() => currentNote.value !== null);

// Initialize form when selectedNote changes
const initializeForm = () => {
    if (currentNote.value) {
        noteForm.value = {
            title: currentNote.value.title || 'Untitled',
            content: currentNote.value.content || '',
            tags: [...(currentNote.value.tags || [])],
            is_pinned: currentNote.value.is_pinned || false
        };
    }
};

// Watch for selectedNote changes
watch(() => props.selectedNote, (newNote) => {
    currentNote.value = newNote;
    initializeForm();
    isEditing.value = false;
}, { immediate: true });

// Watch for notes changes
watch(() => props.notes, (newNotes) => {
    filteredNotes.value = newNotes;
}, { immediate: true });

// Search functionality - Keep as simple API call
const performSearch = async () => {
    if (searchQuery.value.trim() === '') {
        filteredNotes.value = props.notes;
        return;
    }

    try {
        // Use a simple GET request for search - no CSRF needed
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
    clearTimeout(autoSaveTimeout.value);
    autoSaveTimeout.value = setTimeout(performSearch, 300);
});

// Select a note
const selectNote = (note: Note) => {
    router.get(route('notes.show', note.id), {}, {
        preserveState: true,
        preserveScroll: true
    });
};

// Create new note
const createNewNote = () => {
    router.post(route('notes.create-empty'), {}, {
        onSuccess: () => {
            // Note will be created and user redirected to it
        }
    });
};

// Toggle edit mode
const toggleEdit = async () => {
    if (!currentNote.value) return;

    isEditing.value = !isEditing.value;

    if (isEditing.value) {
        await nextTick();
        // Optional: Focus on title input
        setTimeout(() => {
            try {
                const titleEl = titleInput.value;
                if (titleEl) {
                    // Handle Shadcn Input component - it might have an $el property
                    const inputElement = titleEl.$el?.querySelector('input') || titleEl;
                    if (inputElement && typeof inputElement.focus === 'function') {
                        inputElement.focus();
                    }
                }
            } catch (error) {
                // Silently handle focus errors - not critical for functionality
                console.debug('Title input focus not available');
            }
        }, 100);
    }
};

// Auto-save functionality - Use API endpoint for better performance
const autoSave = async () => {
    if (!currentNote.value || !isEditing.value) return;

    isSaving.value = true;

    try {
        const response = await fetch(route('notes.api.auto-save', currentNote.value.id), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                title: noteForm.value.title,
                content: noteForm.value.content,
            })
        });

        if (response.ok) {
            const data = await response.json();
            // Update the current note's updated_at timestamp
            if (currentNote.value && data.updated_at) {
                currentNote.value.updated_at = data.updated_at;
            }
        } else if (response.status === 419) {
            // CSRF token expired, reload the page
            console.warn('CSRF token expired, reloading page...');
            window.location.reload();
        } else {
            console.error('Auto-save failed with status:', response.status);
        }
    } catch (error) {
        console.error('Auto-save failed:', error);
    } finally {
        isSaving.value = false;
    }
};

// Watch for form changes to trigger auto-save
watch([() => noteForm.value.title, () => noteForm.value.content], () => {
    if (!isEditing.value || !currentNote.value) return;

    clearTimeout(autoSaveTimeout.value);
    autoSaveTimeout.value = setTimeout(autoSave, 1000);
});

// Save note - Use router.visit for better CSRF handling
const saveNote = async () => {
    if (!currentNote.value) return;

    isSaving.value = true;

    // Use Inertia's visit method with method: 'put'
    router.visit(route('notes.update', currentNote.value.id), {
        method: 'put',
        data: noteForm.value,
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            isEditing.value = false;

            // Update current note from flash data if available
            if (page.props.flash?.note) {
                Object.assign(currentNote.value, page.props.flash.note);
            }

            // Update the note in the list
            const noteIndex = filteredNotes.value.findIndex(n => n.id === currentNote.value?.id);
            if (noteIndex !== -1 && page.props.flash?.note) {
                filteredNotes.value[noteIndex] = { ...filteredNotes.value[noteIndex], ...page.props.flash.note };
            }
        },
        onError: (errors) => {
            console.error('Save failed:', errors);
        },
        onFinish: () => {
            isSaving.value = false;
        }
    });
};

// Toggle pin - Use router.visit for better CSRF handling
const togglePin = async (note: Note) => {
    // Use Inertia's visit method with method: 'patch'
    router.visit(route('notes.toggle-pin', note.id), {
        method: 'patch',
        preserveState: true,
        preserveScroll: true,
        only: [], // Don't reload props
        onSuccess: (page) => {
            // Update note in list from flash data
            const noteIndex = filteredNotes.value.findIndex(n => n.id === note.id);
            if (noteIndex !== -1 && page.props.flash?.is_pinned !== undefined) {
                filteredNotes.value[noteIndex].is_pinned = page.props.flash.is_pinned;
            }
            // Update current note if it's the same
            if (currentNote.value?.id === note.id && page.props.flash?.is_pinned !== undefined) {
                currentNote.value.is_pinned = page.props.flash.is_pinned;
                noteForm.value.is_pinned = page.props.flash.is_pinned;
            }
        },
        onError: (errors) => {
            console.error('Toggle pin failed:', errors);
        }
    });
};

// Delete note
const deleteNote = (noteId: number) => {
    router.delete(route('notes.destroy', noteId), {
        onSuccess: () => {
            // Will redirect to notes index
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

// Cancel editing
const cancelEdit = () => {
    isEditing.value = false;
    initializeForm();
};

onMounted(() => {
    initializeForm();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notes" />

        <div class="flex h-[calc(100vh-8rem)] bg-background">
            <!-- Sidebar -->
            <div class="w-80 border-r border-border bg-card">
                <!-- Header -->
                <div class="p-4 border-b border-border">
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
                <ScrollArea class="flex-1 h-[calc(100%-120px)]">
                    <div class="p-2">
                        <div v-if="!hasNotes" class="p-4 text-center text-muted-foreground">
                            <FileText class="h-12 w-12 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No notes found</p>
                            <Button @click="createNewNote" variant="outline" size="sm" class="mt-2">
                                Create your first note
                            </Button>
                        </div>

                        <div v-else class="space-y-1">
                            <Card
                                v-for="note in filteredNotes"
                                :key="note.id"
                                @click="selectNote(note)"
                                class="cursor-pointer transition-colors hover:bg-accent/50 p-3"
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

                                <p class="text-xs text-muted-foreground line-clamp-2 mb-2">
                                    {{ note.content_preview || 'No content' }}
                                </p>

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
                <div v-if="!hasSelectedNote" class="flex-1 flex items-center justify-center">
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
                    <div class="p-4 border-b border-border bg-card">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <Input
                                    v-if="isEditing"
                                    ref="titleInput"
                                    v-model="noteForm.title"
                                    class="text-lg font-semibold border-none p-0 h-auto bg-transparent"
                                    placeholder="Untitled"
                                />
                                <h1 v-else class="text-lg font-semibold">
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

                                <!-- Edit/Save Button -->
                                <Button
                                    v-if="!isEditing"
                                    @click="toggleEdit"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                >
                                    <Edit3 class="h-4 w-4" />
                                </Button>

                                <template v-else>
                                    <Button
                                        @click="saveNote"
                                        size="sm"
                                        :disabled="isSaving"
                                    >
                                        {{ isSaving ? 'Saving...' : 'Save' }}
                                    </Button>
                                    <Button
                                        @click="cancelEdit"
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

                        <!-- Meta Information -->
                        <div class="flex items-center space-x-4 mt-2 text-xs text-muted-foreground">
                            <div class="flex items-center space-x-1">
                                <Clock class="h-3 w-3" />
                                <span>Updated {{ formatDate(currentNote.updated_at) }}</span>
                            </div>
                            <span v-if="currentNote.word_count > 0">{{ currentNote.word_count }} words</span>
                            <span v-if="isSaving" class="text-blue-500">Auto-saving...</span>
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

                    <!-- Note Content -->
                    <div class="flex-1 p-4">
                        <TipTapEditor
                            v-if="isEditing"
                            v-model="noteForm.content"
                            :editable="true"
                            placeholder="Start writing your note... (Type '/' for commands)"
                            class="w-full h-full"
                            @update:modelValue="(value) => noteForm.content = value"
                        />
                        <div
                            v-else
                            @click="toggleEdit"
                            class="w-full h-full cursor-text p-4 rounded hover:bg-accent/20 transition-colors prose prose-sm max-w-none"
                            v-html="currentNote.content || '<p class=&quot;text-muted-foreground&quot;>Click to start writing...</p>'"
                        />

                        <!-- Helper text for slash commands -->
                        <div v-if="isEditing" class="mt-2 px-4 pb-2">
                            <p class="text-xs text-muted-foreground">
                                💡 <strong>Tip:</strong> Type <code class="px-1 py-0.5 bg-muted rounded text-xs">/</code> to insert blocks like headings, lists, tables, and more
                            </p>
                        </div>
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
