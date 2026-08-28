<script setup lang="ts">
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { NoteImage } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Check, Download, ExternalLink, Eye, Image, ImagePlus, Loader2, RefreshCw, Trash2, Upload, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    noteId: number | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'image-selected': [url: string];
}>();

// State management
const uploadTab = ref('upload');
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadError = ref('');
const existingImages = ref<NoteImage[]>([]);
const isLoadingImages = ref(false);
const loadImagesError = ref('');
const selectedExistingImage = ref<NoteImage | null>(null);
const imageUrlInput = ref('');
const urlPreviewError = ref(false);
const previewImage = ref<NoteImage | null>(null);
const isDeletingImage = ref<number | null>(null);

// A new URL gets a fresh preview attempt
watch(imageUrlInput, () => {
    urlPreviewError.value = false;
});

const page = usePage();

// Computed properties
const hasExistingImages = computed(() => existingImages.value.length > 0);
const canInsert = computed(() => {
    if (uploadTab.value === 'library') {
        return !!selectedExistingImage.value;
    } else if (uploadTab.value === 'url') {
        return !!imageUrlInput.value.trim();
    }
    return false;
});

// Utility functions
const resetUploadState = () => {
    isUploading.value = false;
    uploadProgress.value = 0;
    uploadError.value = '';

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const resetDialogState = () => {
    uploadTab.value = 'upload';
    dragOver.value = false;
    uploadError.value = '';
    selectedExistingImage.value = null;
    imageUrlInput.value = '';
    previewImage.value = null;
    resetUploadState();
};

// Dialog management
const updateOpen = (value: boolean) => {
    emit('update:open', value);
    if (!value) {
        resetDialogState();
    }
};

// Load existing images
const loadExistingImages = async () => {
    if (isLoadingImages.value) return;

    isLoadingImages.value = true;
    loadImagesError.value = '';

    try {
        const response = await fetch(route('images.api.index'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.images) {
            existingImages.value = data.images;
        } else {
            existingImages.value = [];
        }
    } catch {
        loadImagesError.value = 'Failed to load existing images';
        toast.error('Error', { description: 'Failed to load existing images' });
        existingImages.value = [];
    } finally {
        isLoadingImages.value = false;
    }
};

// Handle successful upload
const handleUploadSuccess = (imageData: any) => {
    // Add to existing images if not already present
    const exists = existingImages.value.some((img) => img.id === imageData.id);
    if (!exists) {
        existingImages.value = [imageData, ...existingImages.value];
    }

    // Auto-select the uploaded image and switch to library
    selectedExistingImage.value = imageData;
    uploadTab.value = 'library';

    // Reset upload state
    resetUploadState();

    toast.success('Upload successful', {
        description: 'Image uploaded successfully. Click Insert to add it to your note.',
    });
};

// Watch for dialog open/close
watch(
    () => props.open,
    async (open) => {
        if (open && props.noteId) {
            await loadExistingImages();
        }
    },
);

// Watch for flash messages from Inertia
watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash && flash.success && flash.image && isUploading.value) {
            handleUploadSuccess(flash.image);
        }
    },
    { deep: true, immediate: true },
);

// File upload handling
const triggerFileInput = () => {
    if (isUploading.value) return;
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        uploadFile(input.files[0]);
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
        uploadFile(event.dataTransfer.files[0]);
    }
};

const uploadFile = async (file: File) => {
    if (!props.noteId) {
        uploadError.value = 'Note ID is required';
        toast.error('Error', { description: 'Note ID is required' });
        return;
    }

    if (isUploading.value) {
        toast.error('Upload in progress', { description: 'Please wait for the current upload to complete' });
        return;
    }

    if (!file.type.startsWith('image/')) {
        uploadError.value = 'Please select an image file';
        toast.error('Invalid file', { description: 'Please select an image file' });
        return;
    }

    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        uploadError.value = 'Image size must be less than 10MB';
        toast.error('File too large', { description: 'Image size must be less than 10MB' });
        return;
    }

    isUploading.value = true;
    uploadProgress.value = 0;
    uploadError.value = '';

    const formData = new FormData();
    formData.append('image', file);
    formData.append('note_id', props.noteId.toString());

    router.post(route('notes.images.api.store'), formData, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onProgress: (progress) => {
            if (progress.percentage) {
                uploadProgress.value = progress.percentage;
            }
        },
        onSuccess: (page) => {
            // Check if we have flash data in the response
            const flash = page.props.flash;
            if (flash && flash.success && flash.image) {
                handleUploadSuccess(flash.image);
            } else {
                // Fallback: reload images and reset state
                setTimeout(async () => {
                    await loadExistingImages();
                    resetUploadState();
                    uploadTab.value = 'library';
                    toast.success('Upload completed', { description: 'Image uploaded successfully' });
                }, 1000);
            }
        },
        onError: (errors) => {
            uploadError.value = errors.image || Object.values(errors)[0] || 'Failed to upload image';
            toast.error('Upload failed', {
                description: uploadError.value,
            });
            resetUploadState();
        },
        onFinish: () => {
            // This always runs after success or error
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
};

// Image selection and actions
const selectImage = (image: NoteImage) => {
    selectedExistingImage.value = selectedExistingImage.value?.id === image.id ? null : image;
};

const isImageSelected = (image: NoteImage): boolean => {
    return selectedExistingImage.value?.id === image.id;
};

const previewImageModal = (image: NoteImage) => {
    previewImage.value = image;
};

const closePreview = () => {
    previewImage.value = null;
};

const downloadImage = (image: NoteImage) => {
    const link = document.createElement('a');
    link.href = image.url;
    link.download = image.filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    toast.success('Download started', { description: `Downloading ${image.filename}` });
};

const deleteImage = async (image: NoteImage) => {
    if (isDeletingImage.value) return;

    isDeletingImage.value = image.id;

    try {
        await new Promise<void>((resolve, reject) => {
            router.delete(route('notes.images.destroy', image.id), {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    // Remove from local state
                    existingImages.value = existingImages.value.filter((img) => img.id !== image.id);

                    // Clear selection if deleted image was selected
                    if (selectedExistingImage.value?.id === image.id) {
                        selectedExistingImage.value = null;
                    }

                    toast.success('Image deleted', { description: 'Image was deleted successfully' });
                    resolve();
                },
                onError: (errors) => {
                    toast.error('Delete failed', { description: 'Failed to delete the image' });
                    reject(errors);
                },
            });
        });
    } finally {
        isDeletingImage.value = null;
    }
};

// Insert image into editor
const insertImage = () => {
    if (uploadTab.value === 'library' && selectedExistingImage.value) {
        emit('image-selected', selectedExistingImage.value.url);
        updateOpen(false);
    } else if (uploadTab.value === 'url' && imageUrlInput.value.trim()) {
        try {
            new URL(imageUrlInput.value.trim());
            emit('image-selected', imageUrlInput.value.trim());
            updateOpen(false);
        } catch {
            toast.error('Invalid URL', { description: 'Please enter a valid image URL' });
        }
    } else {
        toast.error('No selection', { description: 'Please select an image or enter a URL' });
    }
};

// Format file size
const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>

<template>
    <Dialog :open="props.open" @update:open="updateOpen">
        <DialogContent class="max-h-[90vh] overflow-hidden sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>Insert image</DialogTitle>
                <DialogDescription> Add an image to your note from your device, existing library, or web URL. </DialogDescription>
            </DialogHeader>

            <Tabs v-model="uploadTab" class="mt-4 flex-1 overflow-hidden">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="upload" class="flex items-center gap-2">
                        <Upload class="h-4 w-4" />
                        Upload
                    </TabsTrigger>
                    <TabsTrigger value="library" class="flex items-center gap-2">
                        <Image class="h-4 w-4" />
                        Library ({{ existingImages.length }})
                    </TabsTrigger>
                    <TabsTrigger value="url" class="flex items-center gap-2">
                        <ExternalLink class="h-4 w-4" />
                        URL
                    </TabsTrigger>
                </TabsList>

                <!-- Upload Tab -->
                <TabsContent value="upload" class="py-4">
                    <div
                        role="button"
                        :tabindex="isUploading ? -1 : 0"
                        aria-label="Upload an image"
                        class="focus-visible:ring-ring/50 rounded-lg border-2 border-dashed p-6 text-center transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        :class="{
                            'border-primary bg-primary/5': dragOver,
                            'border-border hover:border-muted-foreground/30': !dragOver && !isUploading,
                            'cursor-pointer': !isUploading,
                            'border-border cursor-not-allowed opacity-60': isUploading,
                        }"
                        @click="triggerFileInput"
                        @keydown.enter.prevent="triggerFileInput"
                        @keydown.space.prevent="triggerFileInput"
                        @dragover="handleDragOver"
                        @dragleave="handleDragLeave"
                        @drop="handleDrop"
                    >
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" :disabled="isUploading" @change="handleFileSelect" />

                        <div v-if="isUploading" class="flex flex-col items-center py-4">
                            <Loader2 class="text-primary mb-3 size-8 animate-spin" />
                            <div class="mb-1 text-sm font-medium">Uploading…</div>
                            <div class="text-muted-foreground mb-3 text-sm tabular-nums">{{ uploadProgress }}% complete</div>
                            <div class="bg-muted h-1.5 w-full max-w-sm overflow-hidden rounded-full">
                                <div
                                    class="bg-primary h-full rounded-full transition-all duration-150"
                                    :style="{ width: `${uploadProgress}%` }"
                                ></div>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center py-6">
                            <ImagePlus class="text-muted-foreground mb-3 size-10" />
                            <h3 class="mb-1 text-sm font-semibold">Upload an image</h3>
                            <p class="text-muted-foreground text-sm">
                                <span class="text-foreground font-medium">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">SVG, PNG, JPG or GIF (max. 10MB)</p>
                        </div>

                        <div v-if="uploadError" class="border-destructive/20 bg-destructive/10 mt-4 rounded-md border p-3">
                            <div class="text-destructive flex items-center gap-2 text-sm">
                                <XCircle class="size-4" />
                                {{ uploadError }}
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Library Tab -->
                <TabsContent value="library" class="h-96 overflow-hidden py-4">
                    <div v-if="isLoadingImages" class="flex h-full items-center justify-center">
                        <div class="text-muted-foreground flex items-center gap-3">
                            <Loader2 class="size-6 animate-spin" />
                            <span class="text-sm">Loading images…</span>
                        </div>
                    </div>

                    <div v-else-if="loadImagesError" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <XCircle class="text-destructive mx-auto mb-3 size-10" />
                            <h3 class="mb-1 text-sm font-semibold">Failed to load images</h3>
                            <p class="text-muted-foreground mb-4 text-sm">{{ loadImagesError }}</p>
                            <Button variant="outline" size="sm" @click="loadExistingImages">
                                <RefreshCw class="mr-2 size-4" />
                                Try again
                            </Button>
                        </div>
                    </div>

                    <div v-else-if="!hasExistingImages" class="flex h-full items-center justify-center">
                        <div class="text-center">
                            <Image class="text-muted-foreground/40 mx-auto mb-3 size-10" />
                            <h3 class="mb-1 text-sm font-semibold">No images yet</h3>
                            <p class="text-muted-foreground mb-4 text-sm">Upload an image first to see it here.</p>
                            <Button variant="outline" size="sm" @click="uploadTab = 'upload'">
                                <Upload class="mr-2 size-4" />
                                Upload image
                            </Button>
                        </div>
                    </div>

                    <!-- Fills the tab pane instead of a fixed 500px that used to clip inside the h-96 container -->
                    <div v-else class="flex h-full flex-col">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-medium tabular-nums">{{ existingImages.length }} in library</h3>
                            <Button variant="outline" size="sm" @click="loadExistingImages" :disabled="isLoadingImages">
                                <RefreshCw class="mr-2 size-4" :class="{ 'animate-spin': isLoadingImages }" />
                                Refresh
                            </Button>
                        </div>

                        <ScrollArea class="min-h-0 flex-1">
                            <div class="grid grid-cols-3 gap-3 p-1 md:grid-cols-4">
                                <div
                                    v-for="image in existingImages"
                                    :key="image.id"
                                    class="group relative overflow-hidden rounded-lg border-2 transition-colors duration-150"
                                    :class="{
                                        'border-primary ring-primary/20 ring-2': isImageSelected(image),
                                        'border-border hover:border-muted-foreground/30': !isImageSelected(image),
                                    }"
                                >
                                    <!-- Image -->
                                    <button
                                        type="button"
                                        class="focus-visible:ring-ring/50 relative block aspect-square w-full cursor-pointer focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                        :aria-label="isImageSelected(image) ? `Deselect ${image.filename}` : `Select ${image.filename}`"
                                        :aria-pressed="isImageSelected(image)"
                                        @click="selectImage(image)"
                                    >
                                        <img :src="image.url" :alt="image.filename" class="h-full w-full object-cover" loading="lazy" />

                                        <!-- Selection indicator -->
                                        <div
                                            v-if="isImageSelected(image)"
                                            class="bg-primary absolute top-2 left-2 flex size-6 items-center justify-center rounded-full"
                                        >
                                            <Check class="text-primary-foreground size-4" />
                                        </div>
                                    </button>

                                    <!-- Quick actions (visible on hover and keyboard focus) -->
                                    <div
                                        class="pointer-events-none absolute inset-x-0 top-0 flex justify-end gap-1 p-1.5 opacity-0 transition-opacity duration-150 group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100"
                                    >
                                        <Button
                                            @click.stop="previewImageModal(image)"
                                            variant="secondary"
                                            size="icon"
                                            class="size-8 border-0 bg-black/60 text-white hover:bg-black/80 hover:text-white"
                                            title="Preview image"
                                            aria-label="Preview image"
                                        >
                                            <Eye class="size-4" />
                                        </Button>

                                        <Button
                                            @click.stop="downloadImage(image)"
                                            variant="secondary"
                                            size="icon"
                                            class="size-8 border-0 bg-black/60 text-white hover:bg-black/80 hover:text-white"
                                            title="Download image"
                                            aria-label="Download image"
                                        >
                                            <Download class="size-4" />
                                        </Button>

                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    @click.stop
                                                    variant="destructive"
                                                    size="icon"
                                                    class="size-8 border-0"
                                                    :disabled="isDeletingImage === image.id"
                                                    title="Delete image"
                                                    aria-label="Delete image"
                                                >
                                                    <Loader2 v-if="isDeletingImage === image.id" class="size-4 animate-spin" />
                                                    <Trash2 v-else class="size-4" />
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>Delete image</AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        "{{ image.filename }}" will be permanently deleted and removed from all notes. This cannot be
                                                        undone.
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

                                    <!-- Image info -->
                                    <div class="bg-card p-2">
                                        <p class="truncate text-xs font-medium" :title="image.filename">
                                            {{ image.filename }}
                                        </p>
                                        <p class="text-muted-foreground text-xs tabular-nums">
                                            {{ formatFileSize(image.size || 0) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </div>
                </TabsContent>

                <!-- URL Tab -->
                <TabsContent value="url" class="space-y-4 py-4">
                    <div>
                        <Label for="imageUrlInput">Image URL</Label>
                        <Input
                            id="imageUrlInput"
                            type="url"
                            v-model="imageUrlInput"
                            placeholder="https://example.com/image.jpg"
                            autocomplete="off"
                            class="mt-2"
                        />
                        <p class="text-muted-foreground mt-2 text-xs">Enter a direct link to an image file (JPG, PNG, GIF, SVG, WebP)</p>
                    </div>

                    <div v-if="imageUrlInput.trim()" class="bg-muted/20 rounded-lg border p-4">
                        <h4 class="mb-2 text-sm font-medium">Preview</h4>
                        <div class="bg-muted flex aspect-video items-center justify-center overflow-hidden rounded-md border">
                            <img
                                v-show="!urlPreviewError"
                                :src="imageUrlInput.trim()"
                                alt="URL preview"
                                class="max-h-full max-w-full object-contain"
                                @error="urlPreviewError = true"
                                @load="urlPreviewError = false"
                            />
                            <div v-if="urlPreviewError" class="text-destructive flex items-center gap-2 px-4 text-sm">
                                <XCircle class="size-4 shrink-0" />
                                Couldn't load an image from this URL. Check the link and try again.
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>

            <DialogFooter class="border-t pt-6">
                <div class="flex w-full items-center justify-between">
                    <div class="text-muted-foreground text-sm">
                        <span v-if="uploadTab === 'library' && selectedExistingImage"> Selected: {{ selectedExistingImage.filename }} </span>
                        <span v-else-if="uploadTab === 'url' && imageUrlInput.trim()"> URL ready to insert </span>
                        <span v-else>
                            {{
                                uploadTab === 'library'
                                    ? 'Select an image from the library'
                                    : uploadTab === 'url'
                                      ? 'Enter an image URL above'
                                      : 'Upload an image to continue'
                            }}
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="updateOpen(false)"> Cancel </Button>
                        <Button type="button" :disabled="!canInsert || isUploading" @click="insertImage">
                            <Loader2 v-if="isUploading" class="mr-2 size-4 animate-spin" />
                            Insert image
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Image Preview Modal -->
    <Dialog :open="!!previewImage" @update:open="closePreview">
        <DialogContent class="max-h-[90vh] overflow-hidden sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{ previewImage?.filename }}</DialogTitle>
                <DialogDescription> {{ formatFileSize(previewImage?.size || 0) }} · Click the image to close </DialogDescription>
            </DialogHeader>

            <div v-if="previewImage" class="flex-1 overflow-hidden">
                <div class="bg-muted/20 flex max-h-[60vh] w-full cursor-pointer items-center justify-center rounded-lg" @click="closePreview">
                    <img :src="previewImage.url" :alt="previewImage.filename" class="max-h-full max-w-full rounded object-contain" />
                </div>
            </div>

            <DialogFooter>
                <div class="flex w-full items-center justify-between">
                    <Button variant="outline" @click="downloadImage(previewImage!)" class="flex items-center gap-2">
                        <Download class="size-4" />
                        Download
                    </Button>

                    <div class="flex gap-2">
                        <Button variant="outline" @click="closePreview"> Close </Button>
                        <Button
                            @click="
                                selectImage(previewImage!);
                                closePreview();
                                uploadTab = 'library';
                            "
                        >
                            Select this image
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
