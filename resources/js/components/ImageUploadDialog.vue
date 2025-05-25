<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Image, Upload, ImagePlus, XCircle, Loader2, ExternalLink } from 'lucide-vue-next'
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
const page = usePage()

const hasExistingImages = computed(() => existingImages.value.length > 0)
const flashMessage = computed(() => page.props.flash)

// Watch for flash messages from the server
watch(() => flashMessage.value, (flash) => {
    if (flash && flash.success && flash.image) {
        const newImage = flash.image
        const exists = existingImages.value.some(img => img.id === newImage.id)
        if (!exists) {
            existingImages.value = [newImage, ...existingImages.value]
        }

        selectedExistingImage.value = newImage
        uploadTab.value = 'library'
    }
}, { deep: true })

const updateOpen = (value: boolean) => {
    emit('update:open', value)

    if (!value) {
        uploadTab.value = 'upload'
        dragOver.value = false
        uploadError.value = ''
        selectedExistingImage.value = null
        imageUrlInput.value = ''
    }
}

watch(() => props.open, async (open) => {
    if (open && props.noteId) {
        await loadExistingImages()
    }
}, { immediate: true })

const loadExistingImages = async () => {
    isLoadingImages.value = true
    loadImagesError.value = ''

    try {
        const response = await fetch(route('images.api.index'), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        const data = await response.json()

        if (data.images) {
            existingImages.value = data.images
        }
    } catch (error) {
        console.error('Failed to load images:', error)
        loadImagesError.value = 'Failed to load existing images'
        toast.error('Error', { description: 'Failed to load existing images' })
    } finally {
        isLoadingImages.value = false
    }
}

const triggerFileInput = () => {
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

    const form = new FormData()
    form.append('image', file)
    form.append('note_id', props.noteId.toString())

    router.post(route('notes.images.api.store'), form, {
        forceFormData: true,
        onProgress: (progress) => {
            if (progress.percentage) {
                uploadProgress.value = progress.percentage
            }
        },
        onSuccess: (page) => {
            const response = page.props.flash
            if (response && response.success && response.image) {
                toast.success('Upload successful', { description: 'Image uploaded successfully' })
            }
            isUploading.value = false
        },
        onError: (errors) => {
            console.error('Upload error:', errors)
            uploadError.value = errors.image || 'Failed to upload image'
            toast.error('Upload failed', {
                description: errors.image || 'An error occurred during upload'
            })
            isUploading.value = false
        }
    })
}

const selectImage = (image: NoteImage) => {
    selectedExistingImage.value = image
}

const insertImage = () => {
    if (uploadTab.value === 'library' && selectedExistingImage.value) {
        emit('image-selected', selectedExistingImage.value.url)
        updateOpen(false)
    } else if (uploadTab.value === 'url' && imageUrlInput.value) {
        emit('image-selected', imageUrlInput.value)
        updateOpen(false)
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="updateOpen">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Insert Image</DialogTitle>
                <DialogDescription>
                    Add an image to your note from your device, existing library, or web URL.
                </DialogDescription>
            </DialogHeader>

            <Tabs v-model="uploadTab" class="mt-4">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="upload" class="flex items-center gap-2">
                        <Upload class="h-4 w-4" />
                        Upload
                    </TabsTrigger>
                    <TabsTrigger value="library" class="flex items-center gap-2">
                        <Image class="h-4 w-4" />
                        Library
                    </TabsTrigger>
                    <TabsTrigger value="url" class="flex items-center gap-2">
                        <ExternalLink class="h-4 w-4" />
                        URL
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="upload" class="py-4">
                    <div
                        class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors"
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
                            class="hidden"
                            @change="handleFileSelect"
                        />

                        <div v-if="isUploading" class="py-4 flex flex-col items-center">
                            <Loader2 class="h-10 w-10 text-muted-foreground animate-spin mb-2" />
                            <div class="text-sm text-muted-foreground">Uploading... {{ uploadProgress }}%</div>
                            <div class="w-full h-2 bg-muted mt-2 rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full" :style="{ width: `${uploadProgress}%` }"></div>
                            </div>
                        </div>

                        <div v-else class="py-4 flex flex-col items-center">
                            <ImagePlus class="h-10 w-10 text-muted-foreground mb-2" />
                            <p class="text-muted-foreground mb-1">
                                <span class="font-medium text-foreground">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-muted-foreground">
                                SVG, PNG, JPG or GIF (max. 10MB)
                            </p>

                            <div v-if="uploadError" class="mt-4 text-sm text-destructive">
                                {{ uploadError }}
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="library" class="py-4">
                    <div v-if="isLoadingImages" class="py-6 flex justify-center">
                        <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>

                    <div v-else-if="loadImagesError" class="py-6 text-center text-destructive">
                        {{ loadImagesError }}
                    </div>

                    <div v-else-if="!hasExistingImages" class="py-6 text-center">
                        <Image class="h-10 w-10 text-muted-foreground mx-auto mb-2" />
                        <p class="text-muted-foreground">No images available. Upload an image first.</p>
                        <Button variant="outline" size="sm" class="mt-2" @click="uploadTab = 'upload'">
                            Upload Image
                        </Button>
                    </div>

                    <div v-else class="space-y-4">
                        <ScrollArea class="h-80">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <div
                                    v-for="image in existingImages"
                                    :key="image.id"
                                    class="relative group cursor-pointer border rounded-md overflow-hidden"
                                    :class="{ 'ring-3 ring-pink-500 ring-offset-0 mt-1 ml-1': selectedExistingImage?.id === image.id }"
                                    @click="selectImage(image)"
                                >
                                    <img
                                        :src="image.url"
                                        :alt="image.filename"
                                        class="w-full h-32 object-cover"
                                    />
                                </div>
                            </div>
                        </ScrollArea>
                    </div>
                </TabsContent>

                <TabsContent value="url" class="py-4 space-y-4">
                    <div>
                        <Label for="imageUrlInput">Image URL</Label>
                        <Input
                            id="imageUrlInput"
                            type="url"
                            v-model="imageUrlInput"
                            placeholder="https://example.com/image.jpg"
                            autocomplete="off"
                        />
                    </div>
                </TabsContent>
            </Tabs>

            <DialogFooter class="pt-6">
                <Button
                    type="button"
                    variant="outline"
                    @click="updateOpen(false)"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="(uploadTab === 'library' && !selectedExistingImage) && (uploadTab !== 'library' && !imageUrlInput)"
                    @click="insertImage"
                >
                    Insert
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

