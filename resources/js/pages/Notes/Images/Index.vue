<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { BreadcrumbItem, NoteImage } from '@/types';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { toast } from 'vue-sonner';
import {
    Image,
    Upload,
    Trash2,
    ArrowLeft,
    Download,
    MoreHorizontal,
    Search,
    XCircle,
    Copy,
    SortAsc,
    SortDesc,
    Calendar,
    FileType
} from 'lucide-vue-next';

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
const dragOver = ref(false);
const searchQuery = ref('');
const sortBy = ref<'date' | 'name' | 'size'>('date');
const sortDirection = ref<'asc' | 'desc'>('desc');
const selectedImages = ref<number[]>([]);
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
        result = result.filter(image =>
            image.filename.toLowerCase().includes(query) ||
            image.mime_type.toLowerCase().includes(query)
        );
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
        minute: '2-digit'
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

const handleDragLeave = () => {
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
        toast.error('Error', { description: 'Note ID is required' });
        return;
    }

    const validFiles = Array.from(files).filter(file => {
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

    // Create a form for each file and submit using Inertia
    validFiles.forEach((file, index) => {
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
                toast.success('Upload complete', {
                    description: `${file.name} uploaded successfully`
                });

                // If last file, reset uploading state
                if (index === validFiles.length - 1) {
                    isUploading.value = false;
                }
            },
            onError: (errors) => {
                toast.error('Upload failed', {
                    description: errors.image || 'An error occurred during upload'
                });

                // If last file, reset uploading state
                if (index === validFiles.length - 1) {
                    isUploading.value = false;
                }
            },
            preserveState: true,
            preserveScroll: true
        });
    });
};

const deleteImage = (image: NoteImage) => {
    router.delete(route('notes.images.destroy', image.id), {
        onSuccess: () => {
            toast.success('Image deleted', { description: 'Image was deleted successfully' });
        },
        onError: () => {
            toast.error('Delete failed', { description: 'Failed to delete the image' });
        },
        preserveScroll: true
    });
};

const toggleSelectImage = (imageId: number) => {
    const index = selectedImages.value.indexOf(imageId);
    if (index === -1) {
        selectedImages.value.push(imageId);
    } else {
        selectedImages.value.splice(index, 1);
    }
};

const selectAllImages = () => {
    if (selectedImages.value.length === filteredImages.value.length) {
        // Deselect all
        selectedImages.value = [];
    } else {
        // Select all
        selectedImages.value = filteredImages.value.map(img => img.id);
    }
};

const isSelected = (imageId: number): boolean => {
    return selectedImages.value.includes(imageId);
};

const copyImageUrl = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('URL copied', { description: 'Image URL copied to clipboard' });
    } catch (error) {
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

        <div class="container mx-auto py-6 px-4 md:px-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <Button variant="outline" size="icon" class="mr-3" @click="goBack">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <h1 class="text-2xl font-semibold">Images for {{ props.note.title }}</h1>
                </div>

                <div class="flex items-center space-x-2">
                    <Button
                        @click="selectAllImages"
                        variant="outline"
                        size="sm"
                        v-if="props.images.length > 0"
                    >
                        {{ selectedImages.length === filteredImages.length ? 'Deselect All' : 'Select All' }}
                    </Button>
                </div>
            </div>

            <!-- Upload Area -->
            <div
                class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors mb-6"
                :class="{ 'border-primary bg-primary/5': dragOver, 'border-muted-foreground/20': !dragOver }"
                @click="triggerFileInput"
                @dragover="handleDragOver"
                @dragleave="handleDragLeave"
                @drop="handleDrop"
            >
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    class="hidden"
                    @change="handleFileSelect"
                />

                <div v-if="isUploading" class="py-4 flex flex-col items-center">
                    <Loader2 class="h-10 w-10 text-muted-foreground animate-spin mb-2" />
                    <div class="text-sm text-muted-foreground">Uploading... {{ uploadProgress }}%</div>
                    <div class="w-full max-w-md h-2 bg-muted mt-2 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full" :style="{ width: `${uploadProgress}%` }"></div>
                    </div>
                </div>

                <div v-else class="py-4 flex flex-col items-center">
                    <Upload class="h-10 w-10 text-muted-foreground mb-2" />
                    <p class="text-muted-foreground mb-1">
                        <span class="font-medium text-foreground">Click to upload</span> or drag and drop
                    </p>
                    <p class="text-xs text-muted-foreground">
                        SVG, PNG, JPG or GIF (max. 10MB)
                    </p>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="flex flex-col md:flex-row items-center justify-between mb-4 gap-4">
                <div class="relative w-full md:w-64">
                    <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search images..."
                        class="pl-10 py-2 pr-4 block w-full rounded-md border border-input bg-background shadow-sm focus:border-primary focus:ring-1 focus:ring-primary text-sm"
                    />
                </div>

                <div class="flex items-center space-x-2">
                    <Button
                        @click="toggleSort('date')"
                        variant="outline"
                        size="sm"
                        class="flex items-center gap-1"
                        :class="{ 'bg-primary/10': sortBy === 'date' }"
                    >
                        <Calendar class="h-4 w-4" />
                        Date
                        <SortAsc v-if="sortBy === 'date' && sortDirection === 'asc'" class="h-3 w-3" />
                        <SortDesc v-if="sortBy === 'date' && sortDirection === 'desc'" class="h-3 w-3" />
                    </Button>

                    <Button
                        @click="toggleSort('name')"
                        variant="outline"
                        size="sm"
                        class="flex items-center gap-1"
                        :class="{ 'bg-primary/10': sortBy === 'name' }"
                    >
                        <FileType class="h-4 w-4" />
                        Name
                        <SortAsc v-if="sortBy === 'name' && sortDirection === 'asc'" class="h-3 w-3" />
                        <SortDesc v-if="sortBy === 'name' && sortDirection === 'desc'" class="h-3 w-3" />
                    </Button>

                    <Button
                        @click="toggleSort('size')"
                        variant="outline"
                        size="sm"
                        class="flex items-center gap-1"
                        :class="{ 'bg-primary/10': sortBy === 'size' }"
                    >
                        <FileType class="h-4 w-4" />
                        Size
                        <SortAsc v-if="sortBy === 'size' && sortDirection === 'asc'" class="h-3 w-3" />
                        <SortDesc v-if="sortBy === 'size' && sortDirection === 'desc'" class="h-3 w-3" />
                    </Button>
                </div>
            </div>

            <!-- Images Grid -->
            <div v-if="props.images.length === 0" class="bg-card rounded-lg p-6 text-center">
                <Image class="h-16 w-16 text-muted-foreground/30 mx-auto mb-2" />
                <h3 class="text-lg font-medium mb-1">No images found</h3>
                <p class="text-muted-foreground mb-4">Upload images to your note for display here.</p>
                <Button @click="triggerFileInput">Upload Images</Button>
            </div>

            <div v-else>
                <div v-if="filteredImages.length === 0" class="bg-card rounded-lg p-6 text-center">
                    <Search class="h-16 w-16 text-muted-foreground/30 mx-auto mb-2" />
                    <h3 class="text-lg font-medium mb-1">No matching images</h3>
                    <p class="text-muted-foreground mb-4">Try adjusting your search or filters.</p>
                    <Button variant="outline" @click="searchQuery = ''">Clear Search</Button>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <Card
                        v-for="image in filteredImages"
                        :key="image.id"
                        class="overflow-hidden group relative"
                        :class="{ 'ring-2 ring-primary': isSelected(image.id) }"
                    >
                        <!-- Selection Overlay -->
                        <div
                            class="absolute top-2 left-2 z-10"
                            @click.stop="toggleSelectImage(image.id)"
                        >
                            <div
                                class="h-5 w-5 rounded-full border-2 flex items-center justify-center"
                                :class="isSelected(image.id) ? 'bg-primary border-primary' : 'border-white bg-black/20'"
                            >
                                <svg v-if="isSelected(image.id)" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" class="text-white"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </div>
                        </div>

                        <!-- Image Preview -->
                        <div class="relative h-48 bg-muted">
                            <img
                                :src="image.url"
                                :alt="image.filename"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            />

                            <!-- Image Actions Overlay -->
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/60 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                                <div class="flex space-x-1">
                                    <Button
                                        @click="copyImageUrl(image.url)"
                                        variant="secondary"
                                        size="icon"
                                        class="h-8 w-8"
                                    >
                                        <Copy class="h-4 w-4" />
                                    </Button>

                                    <Button
                                        @click="downloadImage(image)"
                                        variant="secondary"
                                        size="icon"
                                        class="h-8 w-8"
                                    >
                                        <Download class="h-4 w-4" />
                                    </Button>

                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                variant="destructive"
                                                size="icon"
                                                class="h-8 w-8"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>Delete Image</AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    Are you sure you want to delete this image? This action cannot be undone.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                <AlertDialogAction
                                                    @click="deleteImage(image)"
                                                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                >
                                                    Delete
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </div>
                            </div>
                        </div>

                        <!-- Image Info -->
                        <div class="p-3">
                            <h3 class="font-medium text-sm truncate" :title="image.filename">
                                {{ image.filename }}
                            </h3>
                            <div class="flex items-center justify-between mt-1 text-xs text-muted-foreground">
                                <span>{{ formatFileSize(image.size) }}</span>
                                <span>{{ formatDate(image.created_at) }}</span>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
