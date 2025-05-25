<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from '@/components/ui/dialog'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
    Image,
    Upload,
    ImagePlus,
    XCircle,
    Loader2,
    ExternalLink,
    Trash2,
    Eye,
    Download,
    Check,
    RefreshCw
} from 'lucide-vue-next'
import { NoteImage } from '@/types'
import { toast } from 'vue-sonner'

const props = defineProps<{
    open: boolean
    noteId: number | null
}>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    'image-selected': [url: string]
}>()

// State management
const uploadTab = ref('upload')
const fileInput = ref<HTMLInputElement | null>(null)
const dragOver = ref(false)
const isUploading = ref(false)
const uploadProgress = ref(0)
const uploadError = ref('')
const existingImages = ref<NoteImage[]>([])
const isLoadingImages = ref(false)
const loadImagesError = ref('')
const selectedExistingImage = ref<NoteImage | null>(null)
const imageUrlInput = ref('')
const previewImage = ref<NoteImage | null>(null)
const isDeletingImage = ref<number | null>(null)

const page = usePage()

// Computed properties
const hasExistingImages = computed(() => existingImages.value.length > 0)
const canInsert = computed(() => {
    if (uploadTab.value === 'library') {
        return !!selectedExistingImage.value
    } else if (uploadTab.value === 'url') {
        return !!imageUrlInput.value.trim()
    }
    return false
})

// Utility functions
const resetUploadState = () => {
    isUploading.value = false
    uploadProgress.value = 0
    uploadError.value = ''

    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const resetDialogState = () => {
    uploadTab.value = 'upload'
    dragOver.value = false
    uploadError.value = ''
    selectedExistingImage.value = null
    imageUrlInput.value = ''
    previewImage.value = null
    resetUploadState()
}

// Dialog management
const updateOpen = (value: boolean) => {
    emit('update:open', value)
    if (!value) {
        resetDialogState()
    }
}

// Load existing images
const loadExistingImages = async () => {
    if (isLoadingImages.value) return

    isLoadingImages.value = true
    loadImagesError.value = ''

    try {
        const response = await fetch(route('images.api.index'), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }

        const data = await response.json()

        if (data.images) {
            existingImages.value = data.images
        } else {
            existingImages.value = []
        }
    } catch (error) {
        loadImagesError.value = 'Failed to load existing images'
        toast.error('Error', { description: 'Failed to load existing images' })
        existingImages.value = []
    } finally {
        isLoadingImages.value = false
    }
}

// Handle successful upload
const handleUploadSuccess = (imageData: any) => {
    // Add to existing images if not already present
    const exists = existingImages.value.some(img => img.id === imageData.id)
    if (!exists) {
        existingImages.value = [imageData, ...existingImages.value]
    }

    // Auto-select the uploaded image and switch to library
    selectedExistingImage.value = imageData
    uploadTab.value = 'library'

    // Reset upload state
    resetUploadState()

    toast.success('Upload successful', {
        description: 'Image uploaded successfully. Click Insert to add it to your note.'
    })
}

// Watch for dialog open/close
watch(() => props.open, async (open) => {
    if (open && props.noteId) {
        await loadExistingImages()
    }
})

// Watch for flash messages from Inertia
watch(() => page.props.flash, (flash: any) => {
    if (flash && flash.success && flash.image && isUploading.value) {
        handleUploadSuccess(flash.image)
    }
}, { deep: true, immediate: true })

// File upload handling
const triggerFileInput = () => {
    if (isUploading.value) return
    fileInput.value?.click()
}

const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement
    if (input.files && input.files.length > 0) {
        uploadFile(input.files[0])
    }
}

const handleDragOver = (event: DragEvent) => {
    event.preventDefault()
    dragOver.value = true
}

const handleDragLeave = () => {
    dragOver.value = false
}

const handleDrop = (event: DragEvent) => {
    event.preventDefault()
    dragOver.value = false

    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        uploadFile(event.dataTransfer.files[0])
    }
}

const uploadFile = async (file: File) => {
    if (!props.noteId) {
        uploadError.value = 'Note ID is required'
        toast.error('Error', { description: 'Note ID is required' })
        return
    }

    if (isUploading.value) {
        toast.error('Upload in progress', { description: 'Please wait for the current upload to complete' })
        return
    }

    if (!file.type.startsWith('image/')) {
        uploadError.value = 'Please select an image file'
        toast.error('Invalid file', { description: 'Please select an image file' })
        return
    }

    const maxSize = 10 * 1024 * 1024 // 10MB
    if (file.size > maxSize) {
        uploadError.value = 'Image size must be less than 10MB'
        toast.error('File too large', { description: 'Image size must be less than 10MB' })
        return
    }

    isUploading.value = true
    uploadProgress.value = 0
    uploadError.value = ''

    const formData = new FormData()
    formData.append('image', file)
    formData.append('note_id', props.noteId.toString())

    router.post(route('notes.images.api.store'), formData, {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onProgress: (progress) => {
            if (progress.percentage) {
                uploadProgress.value = progress.percentage
            }
        },
        onSuccess: (page) => {
            // Check if we have flash data in the response
            const flash = page.props.flash
            if (flash && flash.success && flash.image) {
                handleUploadSuccess(flash.image)
            } else {
                // Fallback: reload images and reset state
                setTimeout(async () => {
                    await loadExistingImages()
                    resetUploadState()
                    uploadTab.value = 'library'
                    toast.success('Upload completed', { description: 'Image uploaded successfully' })
                }, 1000)
            }
        },
        onError: (errors) => {
            uploadError.value = errors.image || Object.values(errors)[0] || 'Failed to upload image'
            toast.error('Upload failed', {
                description: uploadError.value
            })
            resetUploadState()
        },
        onFinish: () => {
            // This always runs after success or error
            if (fileInput.value) {
                fileInput.value.value = ''
            }
        }
    })
}

// Image selection and actions
const selectImage = (image: NoteImage) => {
    selectedExistingImage.value = selectedExistingImage.value?.id === image.id ? null : image
}

const isImageSelected = (image: NoteImage): boolean => {
    return selectedExistingImage.value?.id === image.id
}

const previewImageModal = (image: NoteImage) => {
    previewImage.value = image
}

const closePreview = () => {
    previewImage.value = null
}

const downloadImage = (image: NoteImage) => {
    const link = document.createElement('a')
    link.href = image.url
    link.download = image.filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    toast.success('Download started', { description: `Downloading ${image.filename}` })
}

const deleteImage = async (image: NoteImage) => {
    if (isDeletingImage.value) return

    isDeletingImage.value = image.id

    try {
        await new Promise<void>((resolve, reject) => {
            router.delete(route('notes.images.destroy', image.id), {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    // Remove from local state
                    existingImages.value = existingImages.value.filter(img => img.id !== image.id)

                    // Clear selection if deleted image was selected
                    if (selectedExistingImage.value?.id === image.id) {
                        selectedExistingImage.value = null
                    }

                    toast.success('Image deleted', { description: 'Image was deleted successfully' })
                    resolve()
                },
                onError: (errors) => {
                    toast.error('Delete failed', { description: 'Failed to delete the image' })
                    reject(errors)
                }
            })
        })
    } finally {
        isDeletingImage.value = null
    }
}

// Insert image into editor
const insertImage = () => {
    if (uploadTab.value === 'library' && selectedExistingImage.value) {
        emit('image-selected', selectedExistingImage.value.url)
        updateOpen(false)
    } else if (uploadTab.value === 'url' && imageUrlInput.value.trim()) {
        try {
            new URL(imageUrlInput.value.trim())
            emit('image-selected', imageUrlInput.value.trim())
            updateOpen(false)
        } catch {
            toast.error('Invalid URL', { description: 'Please enter a valid image URL' })
        }
    } else {
        toast.error('No selection', { description: 'Please select an image or enter a URL' })
    }
}

// Format file size
const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}
</script>

<template>
    <Dialog :open="props.open" @update:open="updateOpen">
        <DialogContent class="sm:max-w-4xl max-h-[90vh] overflow-hidden">
            <DialogHeader>
                <DialogTitle>Insert Image</DialogTitle>
                <DialogDescription>
                    Add an image to your note from your device, existing library, or web URL.
                </DialogDescription>
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
                        class="border-2 border-dashed rounded-lg p-8 text-center transition-colors"
                        :class="{
                            'border-primary bg-primary/5': dragOver,
                            'border-muted-foreground/20': !dragOver,
                            'cursor-pointer': !isUploading,
                            'cursor-not-allowed opacity-60': isUploading
                        }"
                        @click="triggerFileInput"
                        @dragover="handleDragOver"
                        @dragleave="handleDragLeave"
                        @drop="handleDrop"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            :disabled="isUploading"
                            @change="handleFileSelect"
                        />

                        <div v-if="isUploading" class="py-4 flex flex-col items-center">
                            <Loader2 class="h-12 w-12 text-primary animate-spin mb-4" />
                            <div class="text-lg font-medium mb-2">Uploading...</div>
                            <div class="text-sm text-muted-foreground mb-3">{{ uploadProgress }}% complete</div>
                            <div class="w-full max-w-sm h-2 bg-muted rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-primary rounded-full transition-all duration-300"
                                    :style="{ width: `${uploadProgress}%` }"
                                ></div>
                            </div>
                        </div>

                        <div v-else class="py-8 flex flex-col items-center">
                            <ImagePlus class="h-16 w-16 text-muted-foreground mb-4" />
                            <h3 class="text-lg font-medium mb-2">Upload an image</h3>
                            <p class="text-muted-foreground mb-1">
                                <span class="font-medium text-foreground">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-sm text-muted-foreground">
                                SVG, PNG, JPG or GIF (max. 10MB)
                            </p>
                        </div>

                        <div v-if="uploadError" class="mt-4 p-3 bg-destructive/10 border border-destructive/20 rounded-md">
                            <div class="flex items-center gap-2 text-sm text-destructive">
                                <XCircle class="h-4 w-4" />
                                {{ uploadError }}
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Library Tab -->
                <TabsContent value="library" class="py-4 h-96 overflow-hidden">
                    <div v-if="isLoadingImages" class="h-full flex items-center justify-center">
                        <div class="flex items-center gap-3 text-muted-foreground">
                            <Loader2 class="h-6 w-6 animate-spin" />
                            <span>Loading images...</span>
                        </div>
                    </div>

                    <div v-else-if="loadImagesError" class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <XCircle class="h-12 w-12 text-destructive mx-auto mb-3" />
                            <h3 class="text-lg font-medium text-destructive mb-2">Failed to load images</h3>
                            <p class="text-sm text-muted-foreground mb-4">{{ loadImagesError }}</p>
                            <Button variant="outline" size="sm" @click="loadExistingImages">
                                <RefreshCw class="h-4 w-4 mr-2" />
                                Try Again
                            </Button>
                        </div>
                    </div>

                    <div v-else-if="!hasExistingImages" class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <Image class="h-16 w-16 text-muted-foreground/30 mx-auto mb-4" />
                            <h3 class="text-lg font-medium text-muted-foreground mb-2">No images available</h3>
                            <p class="text-sm text-muted-foreground mb-4">Upload an image first to see it here</p>
                            <Button variant="outline" size="sm" @click="uploadTab = 'upload'">
                                <Upload class="h-4 w-4 mr-2" />
                                Upload Image
                            </Button>
                        </div>
                    </div>

                    <div v-else class="h-[500px] flex flex-col">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium">{{ existingImages.length }} image(s) available</h3>
                            <Button variant="outline" size="sm" @click="loadExistingImages" :disabled="isLoadingImages">
                                <RefreshCw class="h-4 w-4 mr-2" :class="{ 'animate-spin': isLoadingImages }" />
                                Refresh
                            </Button>
                        </div>

                        <ScrollArea class="flex-1 h-[500px]">
                            <div class="grid grid-cols-3 md:grid-cols-4 gap-3 p-1">
                                <div
                                    v-for="image in existingImages"
                                    :key="image.id"
                                    class="group relative border-2 rounded-lg overflow-hidden transition-all hover:shadow-md"
                                    :class="{
                                        'border-primary ring-2 ring-primary/20 shadow-md': isImageSelected(image),
                                        'border-muted hover:border-border': !isImageSelected(image)
                                    }"
                                >
                                    <!-- Image -->
                                    <div
                                        class="relative aspect-square cursor-pointer"
                                        @click="selectImage(image)"
                                    >
                                        <img
                                            :src="image.url"
                                            :alt="image.filename"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        />

                                        <!-- Selection indicator -->
                                        <div
                                            v-if="isImageSelected(image)"
                                            class="absolute top-2 left-2 w-6 h-6 bg-primary rounded-full flex items-center justify-center shadow-lg"
                                        >
                                            <Check class="h-4 w-4 text-white" />
                                        </div>

                                        <!-- Hover overlay with quick actions -->
                                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all flex items-center justify-center opacity-0 group-hover:opacity-100">
                                            <div class="flex gap-2">
                                                <Button
                                                    @click.stop="previewImageModal(image)"
                                                    variant="secondary"
                                                    size="icon"
                                                    class="h-8 w-8 bg-white/20 hover:bg-white/40 text-white border-0"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                </Button>

                                                <Button
                                                    @click.stop="downloadImage(image)"
                                                    variant="secondary"
                                                    size="icon"
                                                    class="h-8 w-8 bg-white/20 hover:bg-white/40 text-white border-0"
                                                >
                                                    <Download class="h-4 w-4" />
                                                </Button>

                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button
                                                            @click.stop
                                                            variant="destructive"
                                                            size="icon"
                                                            class="h-8 w-8 bg-red-500/80 hover:bg-red-500 text-white border-0"
                                                            :disabled="isDeletingImage === image.id"
                                                        >
                                                            <Loader2 v-if="isDeletingImage === image.id" class="h-4 w-4 animate-spin" />
                                                            <Trash2 v-else class="h-4 w-4" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Delete Image</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                Are you sure you want to delete "{{ image.filename }}"? This action cannot be undone and will remove the image from all notes.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction
                                                                @click="deleteImage(image)"
                                                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                                            >
                                                                Delete Image
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Image info -->
                                    <div class="p-2 bg-card">
                                        <p class="text-xs font-medium truncate" :title="image.filename">
                                            {{ image.filename }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatFileSize(image.size || 0) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </div>
                </TabsContent>

                <!-- URL Tab -->
                <TabsContent value="url" class="py-4 space-y-4">
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
                        <p class="text-xs text-muted-foreground mt-2">
                            Enter a direct link to an image file (JPG, PNG, GIF, SVG, WebP)
                        </p>
                    </div>

                    <div v-if="imageUrlInput.trim()" class="border rounded-lg p-4 bg-muted/20">
                        <h4 class="text-sm font-medium mb-2">Preview:</h4>
                        <div class="aspect-video bg-muted rounded border flex items-center justify-center overflow-hidden">
                            <img
                                :src="imageUrlInput.trim()"
                                alt="URL Preview"
                                class="max-w-full max-h-full object-contain"
                                @error="$event.target.style.display = 'none'"
                                @load="$event.target.style.display = 'block'"
                            />
                            <div class="text-muted-foreground text-sm" v-show="false">
                                Loading preview...
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>

            <DialogFooter class="pt-6 border-t">
                <div class="flex items-center justify-between w-full">
                    <div class="text-sm text-muted-foreground">
                        <span v-if="uploadTab === 'library' && selectedExistingImage">
                            Selected: {{ selectedExistingImage.filename }}
                        </span>
                        <span v-else-if="uploadTab === 'url' && imageUrlInput.trim()">
                            URL ready to insert
                        </span>
                        <span v-else>
                            {{ uploadTab === 'library' ? 'Select an image from library' :
                            uploadTab === 'url' ? 'Enter image URL above' : 'Upload an image to continue' }}
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="updateOpen(false)"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            :disabled="!canInsert || isUploading"
                            @click="insertImage"
                        >
                            <Loader2 v-if="isUploading" class="w-4 h-4 mr-2 animate-spin" />
                            Insert Image
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Image Preview Modal -->
    <Dialog :open="!!previewImage" @update:open="closePreview">
        <DialogContent class="sm:max-w-4xl max-h-[90vh] overflow-hidden">
            <DialogHeader>
                <DialogTitle>{{ previewImage?.filename }}</DialogTitle>
                <DialogDescription>
                    {{ formatFileSize(previewImage?.size || 0) }} • Click image to close
                </DialogDescription>
            </DialogHeader>

            <div v-if="previewImage" class="flex-1 overflow-hidden">
                <div
                    class="w-full max-h-[60vh] flex items-center justify-center bg-muted/20 rounded-lg cursor-pointer"
                    @click="closePreview"
                >
                    <img
                        :src="previewImage.url"
                        :alt="previewImage.filename"
                        class="max-w-full max-h-full object-contain rounded"
                    />
                </div>
            </div>

            <DialogFooter>
                <div class="flex items-center justify-between w-full">
                    <Button
                        variant="outline"
                        @click="downloadImage(previewImage!)"
                        class="flex items-center gap-2"
                    >
                        <Download class="h-4 w-4" />
                        Download
                    </Button>

                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            @click="closePreview"
                        >
                            Close
                        </Button>
                        <Button
                            @click="selectImage(previewImage!); closePreview(); uploadTab = 'library'"
                        >
                            Select This Image
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
