<script setup lang="ts">
import ImageUploadDialog from '@/components/ImageUploadDialog.vue';
import { Button } from '@/components/ui/button';
import { NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3';
import { ExternalLink, ImageIcon, MaximizeIcon, MinimizeIcon, Trash2 } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    ...nodeViewProps,
    noteId: String,
});

const selectedImage = ref<HTMLImageElement | null>(null);
const isResizing = ref(false);
const startX = ref(0);
const startWidth = ref(0);
const imageWidth = ref(props.node.attrs.width || 'auto');
const isFullscreen = ref(false);

const imageDialogOpen = ref(false);

const showImageDialog = () => {
    imageDialogOpen.value = true;
};

const handleImageSelected = (imageUrl: string) => {
    props.updateAttributes({ src: imageUrl });
    imageDialogOpen.value = false;
};

// When image is clicked, select it
const selectImage = (event: MouseEvent) => {
    if (!props.editor.isEditable) return;
    selectedImage.value = event.target as HTMLImageElement;
    props.editor.commands.setNodeSelection(props.getPos());
};

// Delete image
const deleteImage = () => {
    props.deleteNode();
};

// Open image in new tab
const openInNewTab = () => {
    window.open(props.node.attrs.src, '_blank');
};

// Toggle fullscreen preview
const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
};

// Escape closes the fullscreen viewer
const onFullscreenKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape') isFullscreen.value = false;
};
watch(isFullscreen, (open) => {
    if (open) {
        document.addEventListener('keydown', onFullscreenKey);
    } else {
        document.removeEventListener('keydown', onFullscreenKey);
    }
});

// Handle image resize — pointer events so it also works with touch/pen
const startResize = (event: PointerEvent) => {
    if (!props.editor.isEditable) return;
    isResizing.value = true;
    startX.value = event.clientX;
    startWidth.value = parseInt(imageWidth.value) || selectedImage.value?.offsetWidth || 0;
    document.addEventListener('pointermove', resizeImage);
    document.addEventListener('pointerup', stopResize);
    event.preventDefault();
};

const resizeImage = (event: PointerEvent) => {
    if (!isResizing.value) return;
    const currentX = event.clientX;
    const diff = currentX - startX.value;
    const newWidth = Math.max(100, startWidth.value + diff);
    imageWidth.value = `${newWidth}px`;
    props.updateAttributes({ width: imageWidth.value });
};

const stopResize = () => {
    isResizing.value = false;
    document.removeEventListener('pointermove', resizeImage);
    document.removeEventListener('pointerup', stopResize);
};

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onFullscreenKey);
    document.removeEventListener('pointermove', resizeImage);
    document.removeEventListener('pointerup', stopResize);
});
</script>

<template>
    <NodeViewWrapper class="image-component group relative">
        <!-- Image controls (dark scrim over imagery works in both themes) -->
        <div
            v-if="props.editor.isEditable"
            class="image-controls absolute top-2 right-2 z-10 flex items-center rounded-md bg-black/70 p-1 opacity-0 transition-opacity duration-150 group-focus-within:opacity-100 group-hover:opacity-100"
        >
            <!-- Replace -->
            <Button
                @click="showImageDialog"
                variant="ghost"
                size="icon"
                class="size-8 text-white hover:bg-white/20 hover:text-white"
                title="Replace image"
                aria-label="Replace image"
            >
                <ImageIcon class="size-4" />
            </Button>

            <!-- Open in new tab -->
            <Button
                @click="openInNewTab"
                variant="ghost"
                size="icon"
                class="size-8 text-white hover:bg-white/20 hover:text-white"
                title="Open in new tab"
                aria-label="Open image in new tab"
            >
                <ExternalLink class="size-4" />
            </Button>

            <!-- Fullscreen -->
            <Button
                @click="toggleFullscreen"
                variant="ghost"
                size="icon"
                class="size-8 text-white hover:bg-white/20 hover:text-white"
                :title="isFullscreen ? 'Exit fullscreen' : 'View fullscreen'"
                :aria-label="isFullscreen ? 'Exit fullscreen' : 'View image fullscreen'"
            >
                <MaximizeIcon v-if="!isFullscreen" class="size-4" />
                <MinimizeIcon v-else class="size-4" />
            </Button>

            <!-- Delete -->
            <Button
                @click="deleteImage"
                variant="ghost"
                size="icon"
                class="hover:bg-destructive size-8 text-white hover:text-white"
                title="Remove image"
                aria-label="Remove image"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <!-- Resize handle -->
        <div
            v-if="props.editor.isEditable"
            class="resize-handle border-border bg-background absolute right-2 bottom-2 size-5 cursor-se-resize rounded-sm border opacity-0 transition-opacity duration-150 group-hover:opacity-100"
            role="presentation"
            @pointerdown="startResize"
        ></div>

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
        <div v-if="isFullscreen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" @click="toggleFullscreen">
            <img :src="props.node.attrs.src" alt="" class="max-h-[90vh] max-w-[90vw] object-contain" />
            <Button
                @click.stop="toggleFullscreen"
                variant="ghost"
                size="icon"
                class="absolute top-4 right-4 text-white hover:bg-white/20 hover:text-white"
                aria-label="Close fullscreen view"
            >
                <MinimizeIcon class="size-6" />
            </Button>
        </div>

        <!-- Image upload dialog -->
        <ImageUploadDialog
            v-model:open="imageDialogOpen"
            :noteId="props.noteId ? Number(props.noteId) : null"
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

/* Selection ring follows the app's accent token so dark mode stays consistent */
.image-component.ProseMirror-selectednode {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.resize-handle {
    touch-action: none;
}

.resize-handle:hover {
    background-color: var(--primary);
}
</style>
