<script setup lang="ts">
import { Hash, Pin } from 'lucide-vue-next';

defineProps<{
    note: any;
    active?: boolean;
}>();

const emit = defineEmits<{ (e: 'select'): void }>();

const formatDate = (dateString: string): string => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now.getTime() - date.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};
</script>

<template>
    <button
        @click="emit('select')"
        type="button"
        class="group border-b-border/60 focus-visible:ring-ring/50 relative flex w-full cursor-pointer flex-col gap-1.5 border-b border-l-2 px-4 py-3 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
        :class="active ? 'border-l-primary bg-muted' : 'hover:bg-muted/60 border-l-transparent'"
    >
        <!-- Title -->
        <div class="flex items-center gap-2">
            <Pin v-if="note.is_pinned" class="text-muted-foreground size-3 shrink-0" />
            <span class="truncate text-sm font-semibold" :class="active ? 'text-foreground' : 'text-foreground/90'">
                {{ note.title || 'Untitled' }}
            </span>
        </div>

        <!-- Preview -->
        <p v-if="note.content_preview" class="note-preview text-muted-foreground text-xs leading-relaxed">
            {{ note.content_preview }}
        </p>

        <!-- Meta -->
        <div class="text-muted-foreground/80 flex items-center gap-2 text-[11px] tabular-nums">
            <span>{{ formatDate(note.updated_at) }}</span>
            <template v-if="note.word_count > 0">
                <span class="text-muted-foreground/40">·</span>
                <span>{{ note.word_count }} words</span>
            </template>
            <span v-if="note.tags?.length > 0" class="text-muted-foreground/80 ml-auto inline-flex items-center gap-1">
                <Hash class="size-2.5" />
                {{ note.tags.length }}
            </span>
        </div>
    </button>
</template>

<style scoped>
.note-preview {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
