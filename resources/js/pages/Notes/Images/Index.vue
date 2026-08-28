<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
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
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, NoteImage } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ALargeSmall,
    ArrowLeft,
    Calendar,
    Copy,
    Download,
    HardDrive,
    Image,
    Loader2,
    Search,
    SortAsc,
    SortDesc,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    note: {
        id: number;
        title: string;
    };
    images: NoteImage[];
}

const props = defineProps<Props>();

// State
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadTotal = ref(0);
const uploadCompleted = ref(0);
const dragOver = ref(false);
const searchQuery = ref('');
const sortBy = ref<'date' | 'name' | 'size'>('date');
const sortDirection = ref<'asc' | 'desc'>('desc');
const deletingId = ref<number | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// Breadcrumbs
const breadcrumbs = ref<BreadcrumbItem[]>([
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Notes', href: route('notes.index') },
    { title: props.note.title, href: route('notes.show', props.note.id) },
    { title: 'Images', href: route('notes.images.index', props.note.id) },
]);

// Computed
const filteredImages = computed(() => {
    let result = [...props.images];

    // Apply search filter
    if (searchQuery.value.trim() !== '') {
        const query = searchQuery.value.toLowerCase();
        result = result.filter((image) => image.filename.toLowerCase().includes(query) || image.mime_type.toLowerCase().includes(query));
    }

    // Apply sorting
    result.sort((a, b) => {
        let comparison = 0;

        if (sortBy.value === 'date') {
            comparison = new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
        } else if (sortBy.value === 'name') {
            comparison = a.filename.localeCompare(b.filename);
        } else if (sortBy.value === 'size') {
            comparison = a.size - b.size;
        }

        return sortDirection.value === 'asc' ? comparison : -comparison;
    });

    return result;
});

const toggleSort = (field: 'date' | 'name' | 'size') => {
    if (sortBy.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDirection.value = 'desc';
    }
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};

// Actions
const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        uploadImages(input.files);
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
    dragOver.value = true;
};

const handleDragLeave = (event: DragEvent) => {
    // Ignore dragleave fired while moving over the dropzone's own children,
    // otherwise the highlight flickers during a drag
    if (event.currentTarget instanceof HTMLElement && event.relatedTarget instanceof Node && event.currentTarget.contains(event.relatedTarget)) {
        return;
    }
    dragOver.value = false;
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    dragOver.value = false;

    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        uploadImages(event.dataTransfer.files);
    }
};

const uploadImages = (files: FileList) => {
    if (!props.note.id) {
        toast.error('Upload failed', { description: 'Note ID is required' });
        return;
    }

    const validFiles = Array.from(files).filter((file) => {
        if (!file.type.startsWith('image/')) {
            toast.error('Invalid file type', { description: `${file.name} is not an image file` });
            return false;
        }

        const maxSize = 10 * 1024 * 1024; // 10MB
        if (file.size > maxSize) {
            toast.error('File too large', { description: `${file.name} exceeds the 10MB limit` });
            return false;
        }

        return true;
    });

    if (validFiles.length === 0) return;

    isUploading.value = true;
    uploadProgress.value = 0;
    uploadTotal.value = validFiles.length;
    uploadCompleted.value = 0;

    // One request per file; the shared busy state clears only when EVERY request
    // has finished (success or error), not just the last-started one
    validFiles.forEach((file) => {
        const form = new FormData();
        form.append('image', file);
        form.append('note_id', props.note.id.toString());

        router.post(route('notes.images.store'), form, {
            forceFormData: true,
            onProgress: (progress) => {
                if (progress.percentage) {
                    uploadProgress.value = progress.percentage;
                }
            },
            onSuccess: () => {
                toast.success('Image uploaded', {
                    description: file.name,
                });
            },
            onError: (errors) => {
                toast.error('Upload failed', {
                    description: errors.image || `Could not upload ${file.name}`,
                });
            },
            onFinish: () => {
                uploadCompleted.value += 1;
                if (uploadCompleted.value >= uploadTotal.value) {
                    isUploading.value = false;
                }
            },
            preserveState: true,
            preserveScroll: true,
        });
    });
};

const deleteImage = (image: NoteImage) => {
    deletingId.value = image.id;
    router.delete(route('notes.images.destroy', image.id), {
        onSuccess: () => {
            toast.success('Image deleted');
        },
        onError: () => {
            toast.error('Delete failed', { description: 'Failed to delete the image' });
        },
        onFinish: () => {
            deletingId.value = null;
        },
        preserveScroll: true,
    });
};

const copyImageUrl = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('URL copied', { description: 'Image URL copied to clipboard' });
    } catch {
        toast.error('Copy failed', { description: 'Failed to copy URL to clipboard' });
    }
};

const downloadImage = (image: NoteImage) => {
    const link = document.createElement('a');
    link.href = image.url;
    link.download = image.filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const goBack = () => {
    router.get(route('notes.show', props.note.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Images for ${props.note.title}`" />

        <PageContainer>
            <PageHeader title="Note images" :description="`Images attached to &quot;${props.note.title}&quot;`" :icon="Image">
                <template #actions>
                    <Button variant="outline" size="sm" @click="goBack">
                        <ArrowLeft class="mr-1.5 size-4" />
                        Back to note
                    </Button>
                    <Button size="sm" @click="triggerFileInput" :disabled="isUploading">
                        <Loader2 v-if="isUploading" class="mr-1.5 size-4 animate-spin" />
                        <Upload v-else class="mr-1.5 size-4" />
                        Upload images
                    </Button>
                </template>
            </PageHeader>

            <div class="space-y-6">
                <!-- Upload Area -->
                <div
                    role="button"
                    tabindex="0"
                    aria-label="Upload images"
                    class="focus-visible:ring-ring/50 cursor-pointer rounded-lg border-2 border-dashed p-6 text-center transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                    :class="dragOver ? 'border-primary bg-primary/5' : 'border-border hover:border-muted-foreground/30'"
                    @click="triggerFileInput"
                    @keydown.enter.prevent="triggerFileInput"
                    @keydown.space.prevent="triggerFileInput"
                    @dragover="handleDragOver"
                    @dragleave="handleDragLeave"
                    @drop="handleDrop"
                >
                    <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="handleFileSelect" />

                    <div v-if="isUploading" class="flex flex-col items-center py-4">
                        <Loader2 class="text-primary mb-2 size-8 animate-spin" />
                        <div v-if="uploadTotal > 1" class="text-muted-foreground text-sm tabular-nums">
                            Uploading {{ Math.min(uploadCompleted + 1, uploadTotal) }} of {{ uploadTotal }} files…
                        </div>
                        <template v-else>
                            <div class="text-muted-foreground text-sm tabular-nums">Uploading… {{ uploadProgress }}%</div>
                            <div class="bg-muted mt-2 h-1.5 w-full max-w-md overflow-hidden rounded-full">
                                <div
                                    class="bg-primary h-full rounded-full transition-all duration-150"
                                    :style="{ width: `${uploadProgress}%` }"
                                ></div>
                            </div>
                        </template>
                    </div>

                    <div v-else class="flex flex-col items-center py-4">
                        <Upload class="text-muted-foreground mb-2 size-8" />
                        <p class="text-muted-foreground text-sm"><span class="text-foreground font-medium">Click to upload</span> or drag and drop</p>
                        <p class="text-muted-foreground mt-1 text-xs">SVG, PNG, JPG or GIF (max. 10MB)</p>
                    </div>
                </div>

                <!-- Search and sort -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:w-64">
                        <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input v-model="searchQuery" type="text" placeholder="Search images…" class="pl-9 text-sm" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            @click="toggleSort('date')"
                            variant="outline"
                            size="sm"
                            class="gap-1"
                            :class="{ 'bg-muted': sortBy === 'date' }"
                            :aria-label="`Sort by date${sortBy === 'date' ? (sortDirection === 'asc' ? ', ascending' : ', descending') : ''}`"
                        >
                            <Calendar class="size-4" />
                            Date
                            <SortAsc v-if="sortBy === 'date' && sortDirection === 'asc'" class="size-3" />
                            <SortDesc v-if="sortBy === 'date' && sortDirection === 'desc'" class="size-3" />
                        </Button>

                        <Button
                            @click="toggleSort('name')"
                            variant="outline"
                            size="sm"
                            class="gap-1"
                            :class="{ 'bg-muted': sortBy === 'name' }"
                            :aria-label="`Sort by name${sortBy === 'name' ? (sortDirection === 'asc' ? ', ascending' : ', descending') : ''}`"
                        >
                            <ALargeSmall class="size-4" />
                            Name
                            <SortAsc v-if="sortBy === 'name' && sortDirection === 'asc'" class="size-3" />
                            <SortDesc v-if="sortBy === 'name' && sortDirection === 'desc'" class="size-3" />
                        </Button>

                        <Button
                            @click="toggleSort('size')"
                            variant="outline"
                            size="sm"
                            class="gap-1"
                            :class="{ 'bg-muted': sortBy === 'size' }"
                            :aria-label="`Sort by size${sortBy === 'size' ? (sortDirection === 'asc' ? ', ascending' : ', descending') : ''}`"
                        >
                            <HardDrive class="size-4" />
                            Size
                            <SortAsc v-if="sortBy === 'size' && sortDirection === 'asc'" class="size-3" />
                            <SortDesc v-if="sortBy === 'size' && sortDirection === 'desc'" class="size-3" />
                        </Button>
                    </div>
                </div>

                <!-- Images Grid -->
                <EmptyState
                    v-if="props.images.length === 0"
                    :icon="Image"
                    title="No images yet"
                    description="Upload images to this note to see them here."
                >
                    <template #action>
                        <Button @click="triggerFileInput" :disabled="isUploading">
                            <Upload class="mr-1.5 size-4" />
                            Upload images
                        </Button>
                    </template>
                </EmptyState>

                <template v-else>
                    <EmptyState v-if="filteredImages.length === 0" :icon="Search" title="No matching images" description="Try adjusting your search.">
                        <template #action>
                            <Button variant="outline" @click="searchQuery = ''">Clear search</Button>
                        </template>
                    </EmptyState>

                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <Card v-for="image in filteredImages" :key="image.id" class="gap-0 overflow-hidden p-0">
                            <!-- Image Preview -->
                            <div class="bg-muted relative h-44">
                                <img :src="image.url" :alt="image.filename" class="h-full w-full object-cover" loading="lazy" />
                            </div>

                            <!-- Image Info -->
                            <div class="px-3 pt-2.5 pb-1">
                                <h3 class="truncate text-sm font-medium" :title="image.filename">
                                    {{ image.filename }}
                                </h3>
                                <div class="text-muted-foreground mt-0.5 flex items-center justify-between text-xs tabular-nums">
                                    <span>{{ formatFileSize(image.size) }}</span>
                                    <span>{{ formatDate(image.created_at) }}</span>
                                </div>
                            </div>

                            <!-- Actions (always visible so touch and keyboard users can reach them) -->
                            <div class="border-border/60 mt-1.5 flex items-center gap-0.5 border-t px-2 py-1.5">
                                <Button
                                    @click="copyImageUrl(image.url)"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-foreground size-8 p-0"
                                    title="Copy image URL"
                                    aria-label="Copy image URL"
                                >
                                    <Copy class="size-4" />
                                </Button>

                                <Button
                                    @click="downloadImage(image)"
                                    variant="ghost"
                                    size="sm"
                                    class="text-muted-foreground hover:text-foreground size-8 p-0"
                                    title="Download image"
                                    aria-label="Download image"
                                >
                                    <Download class="size-4" />
                                </Button>

                                <span class="flex-1"></span>

                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive size-8 p-0"
                                            :disabled="deletingId === image.id"
                                            title="Delete image"
                                            aria-label="Delete image"
                                        >
                                            <Loader2 v-if="deletingId === image.id" class="size-4 animate-spin" />
                                            <Trash2 v-else class="size-4" />
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Delete image</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                "{{ image.filename }}" will be permanently deleted. This cannot be undone.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                            <AlertDialogAction
                                                @click="deleteImage(image)"
                                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                            >
                                                Delete image
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </Card>
                    </div>
                </template>
            </div>
        </PageContainer>
    </AppLayout>
</template>
