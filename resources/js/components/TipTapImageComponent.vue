<script setup lang="ts">
import { computed, ref } from 'vue'
import { NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3'
import { Button } from '@/components/ui/button'
import {
    Trash2,
    MaximizeIcon,
    MinimizeIcon,
    ExternalLink,
    ImageIcon,
} from 'lucide-vue-next'
import ImageUploadDialog from '@/components/ImageUploadDialog.vue'

const props = defineProps({
    ...nodeViewProps,
    noteId: String,
})

const selectedImage = ref<HTMLImageElement | null>(null)
const isResizing = ref(false)
const startX = ref(0)
const startWidth = ref(0)
const imageWidth = ref(props.node.attrs.width || 'auto')
const isFullscreen = ref(false)

const imageDialogOpen = ref(false)

const showImageDialog = () => {
    imageDialogOpen.value = true
}

const handleImageSelected = (imageUrl: string) => {
    props.updateAttributes({ src: imageUrl })
    imageDialogOpen.value = false
}

// When image is clicked, select it
const selectImage = (event: MouseEvent) => {
    if (!props.editor.isEditable) return
    selectedImage.value = event.target as HTMLImageElement
    props.editor.commands.setNodeSelection(props.getPos())
}

// Delete image
const deleteImage = () => {
    props.deleteNode()
}

// Open image in new tab
const openInNewTab = () => {
    window.open(props.node.attrs.src, '_blank')
}

// Toggle fullscreen preview
const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value
}

// Handle image resize
const startResize = (event: MouseEvent) => {
    if (!props.editor.isEditable) return
    isResizing.value = true
    startX.value = event.clientX
    startWidth.value = parseInt(imageWidth.value) || selectedImage.value?.offsetWidth || 0
    document.addEventListener('mousemove', resizeImage)
    document.addEventListener('mouseup', stopResize)
    event.preventDefault()
}

const resizeImage = (event: MouseEvent) => {
    if (!isResizing.value) return
    const currentX = event.clientX
    const diff = currentX - startX.value
    const newWidth = Math.max(100, startWidth.value + diff)
    imageWidth.value = `${newWidth}px`
    props.updateAttributes({ width: imageWidth.value })
}

const stopResize = () => {
    isResizing.value = false
    document.removeEventListener('mousemove', resizeImage)
    document.removeEventListener('mouseup', stopResize)
}


// Determine current alignment
computed(() => {
    const style = props.node.attrs.style || ''
    if (style.includes('margin: 0 auto')) return 'center'
    if (style.includes('float: right')) return 'right'
    if (style.includes('float: left')) return 'left'
    return 'left'
});
</script>

<template>
    <NodeViewWrapper class="image-component relative group">
        <!-- Image controls -->
        <div v-if="props.editor.isEditable"
             class="image-controls absolute top-2 right-2 flex items-center bg-black/70 rounded-md p-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">

            <!-- Replace -->
            <Button @click="showImageDialog" variant="ghost" size="icon" class="text-white h-7 w-7">
                <ImageIcon class="h-4 w-4" />
            </Button>

            <!-- Open in new tab -->
            <Button @click="openInNewTab" variant="ghost" size="icon" class="text-white h-7 w-7">
                <ExternalLink class="h-4 w-4" />
            </Button>

            <!-- Fullscreen -->
            <Button @click="toggleFullscreen" variant="ghost" size="icon" class="text-white h-7 w-7">
                <MaximizeIcon v-if="!isFullscreen" class="h-4 w-4" />
                <MinimizeIcon v-else class="h-4 w-4" />
            </Button>

            <!-- Delete -->
            <Button @click="deleteImage" variant="ghost" size="icon" class="text-white h-7 w-7 hover:text-red-500">
                <Trash2 class="h-4 w-4" />
            </Button>
        </div>

        <!-- Resize handle -->
        <div v-if="props.editor.isEditable"
             class="resize-handle absolute bottom-2 right-2 w-4 h-4 bg-white rounded-sm cursor-se-resize opacity-0 group-hover:opacity-100 transition-opacity"
             @mousedown="startResize"></div>

        <!-- Image -->
        <img
            :src="props.node.attrs.src"
            :alt="props.node.attrs.alt || ''"
            :title="props.node.attrs.title"
            :width="props.node.attrs.width"
            :height="props.node.attrs.height"
            :style="props.node.attrs.style"
            @click="selectImage"
            ref="selectedImage"
            draggable="true"
            class="max-w-full"
        />

        <!-- Fullscreen viewer -->
        <div v-if="isFullscreen" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50" @click="toggleFullscreen">
            <img :src="props.node.attrs.src" alt="" class="max-w-[90vw] max-h-[90vh] object-contain" />
            <Button @click.stop="toggleFullscreen" variant="ghost" size="icon" class="absolute top-4 right-4 text-white">
                <MinimizeIcon class="h-6 w-6" />
            </Button>
        </div>

        <!-- Image upload dialog -->
        <ImageUploadDialog
            v-model:open="imageDialogOpen"
            :noteId="1"
            @image-selected="handleImageSelected"
        />
    </NodeViewWrapper>
</template>

<style scoped>
.image-component {
    display: inline-block;
    position: relative;
}

.image-component img {
    display: block;
}

.image-component.ProseMirror-selectednode {
    outline: 2px solid #3b82f6;
}

.resize-handle:hover {
    background-color: #3b82f6;
}
</style>
