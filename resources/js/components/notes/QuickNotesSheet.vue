<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import NoteItem from '@/components/notes/NoteItem.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, FileText, NotebookText, Pin, Search } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const open = ref(false);
const notes = ref<any[]>([]);
const loaded = ref(false);
const loading = ref(false);
const loadError = ref(false);
const query = ref('');
const selectedId = ref<number | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const fetchNotes = async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const res = await fetch(route('notes.api.search'), { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            notes.value = data.notes || [];
            loaded.value = true;
        } else {
            loadError.value = true;
        }
    } catch {
        // Surface the failure instead of silently showing "No notes yet"
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

const openSheet = () => {
    open.value = true;
    mobileReading.value = false;
    if (!loaded.value) fetchNotes();
    nextTick(() => searchInput.value?.focus());
};

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return notes.value;
    return notes.value.filter((n) => (n.title || '').toLowerCase().includes(q) || (n.content_preview || '').toLowerCase().includes(q));
});

const pinned = computed(() => filtered.value.filter((n) => n.is_pinned));
const others = computed(() => filtered.value.filter((n) => !n.is_pinned));

const selected = computed(() => notes.value.find((n) => n.id === selectedId.value) || null);

// Mobile is single-pane: tapping a note shows the reading view
const mobileReading = ref(false);
const selectNote = (id: number) => {
    selectedId.value = id;
    mobileReading.value = true;
};

// Auto-select first note when opening / filtering
watch([open, filtered], () => {
    if (open.value && filtered.value.length && !filtered.value.some((n) => n.id === selectedId.value)) {
        selectedId.value = filtered.value[0].id;
    }
});

const formatDate = (s: string) => {
    if (!s) return '';
    const d = new Date(s);
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 3600) return `${Math.max(1, Math.floor(diff / 60))}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Keyboard: g then n, or Cmd/Ctrl+Shift+N to open; Esc closes
let gPressed = false;
let gTimer: ReturnType<typeof setTimeout> | null = null;
const onKey = (e: KeyboardEvent) => {
    const t = e.target as HTMLElement;
    const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);

    if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key.toLowerCase() === 'n') {
        e.preventDefault();
        if (open.value) {
            open.value = false;
        } else {
            openSheet();
        }
        return;
    }
    if (typing) return;
    if (e.key.toLowerCase() === 'g') {
        gPressed = true;
        if (gTimer) clearTimeout(gTimer);
        gTimer = setTimeout(() => (gPressed = false), 600);
        return;
    }
    if (gPressed && e.key.toLowerCase() === 'n') {
        e.preventDefault();
        gPressed = false;
        openSheet();
    }
};

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

defineExpose({ openSheet });
</script>

<template>
    <!-- Floating launcher -->
    <Teleport to="body">
        <button
            v-show="!open"
            @click="openSheet"
            type="button"
            class="border-border bg-card text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground focus-visible:ring-ring/50 fixed right-5 bottom-5 z-40 flex size-11 cursor-pointer items-center justify-center rounded-lg border shadow-xs transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
            title="Quick notes (g then n)"
            aria-label="Open quick notes"
        >
            <NotebookText class="size-[18px]" />
        </button>
    </Teleport>

    <Sheet :open="open" @update:open="open = $event">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:!max-w-4xl lg:!max-w-5xl">
            <!-- Header -->
            <div class="border-border flex items-center gap-2 border-b px-5 py-3.5">
                <FileText class="text-primary size-4" />
                <h2 class="text-foreground text-base font-semibold tracking-tight">Quick notes</h2>
                <span class="bg-muted text-muted-foreground rounded-full px-1.5 py-0.5 text-xs font-medium">Read-only</span>
            </div>

            <div class="flex min-h-0 flex-1">
                <!-- List pane -->
                <div class="border-border bg-muted/20 flex w-full shrink-0 flex-col border-r md:w-80" :class="{ 'hidden md:flex': mobileReading }">
                    <div class="border-border border-b p-3">
                        <div class="relative">
                            <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2" />
                            <Input ref="searchInput" v-model="query" placeholder="Search notes…" class="bg-card h-9 rounded-lg pl-8 text-sm" />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <!-- Loading skeleton -->
                        <div v-if="loading && !loaded" class="space-y-4 p-4">
                            <div v-for="i in 4" :key="i" class="space-y-2">
                                <Skeleton class="h-4 w-2/3" />
                                <Skeleton class="h-3 w-full" />
                                <Skeleton class="h-3 w-1/3" />
                            </div>
                        </div>

                        <!-- Load failure with retry -->
                        <div v-else-if="loadError && !loaded" class="flex flex-col items-center gap-3 px-4 py-8 text-center">
                            <p class="text-muted-foreground text-sm">Couldn't load your notes.</p>
                            <Button variant="outline" size="sm" @click="fetchNotes" :disabled="loading">Try again</Button>
                        </div>

                        <div v-else-if="!filtered.length" class="p-4">
                            <EmptyState
                                :icon="query ? Search : FileText"
                                :title="query ? 'No matches' : 'No notes yet'"
                                :description="query ? 'Try a different search.' : 'Notes you create will show up here.'"
                            />
                        </div>

                        <template v-else>
                            <div v-if="pinned.length">
                                <p
                                    class="border-border/60 bg-muted/40 text-muted-foreground flex items-center gap-1.5 border-b px-4 py-1.5 text-[11px] font-medium tracking-wider uppercase"
                                >
                                    <Pin class="size-3" /> Pinned
                                </p>
                                <NoteItem v-for="n in pinned" :key="n.id" :note="n" :active="selectedId === n.id" @select="selectNote(n.id)" />
                            </div>
                            <div v-if="others.length">
                                <p
                                    v-if="pinned.length"
                                    class="border-border bg-muted/40 text-muted-foreground border-y px-4 py-1.5 text-[11px] font-medium tracking-wider uppercase"
                                >
                                    All notes
                                </p>
                                <NoteItem v-for="n in others" :key="n.id" :note="n" :active="selectedId === n.id" @select="selectNote(n.id)" />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Reading pane -->
                <div class="flex min-w-0 flex-1 flex-col" :class="{ 'hidden md:flex': !mobileReading }">
                    <template v-if="selected">
                        <div class="border-border flex items-center justify-between gap-3 border-b px-5 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:bg-muted/60 hover:text-foreground focus-visible:ring-ring/50 mr-0.5 -ml-1 flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none md:hidden"
                                    @click="mobileReading = false"
                                    aria-label="Back to list"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <Pin v-if="selected.is_pinned" class="text-muted-foreground size-3.5 shrink-0" />
                                <h3 class="text-foreground truncate text-base font-semibold tracking-tight">{{ selected.title || 'Untitled' }}</h3>
                            </div>
                            <span class="text-muted-foreground shrink-0 text-xs tabular-nums">{{ formatDate(selected.updated_at) }}</span>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                            <div
                                class="tiptap-prose max-w-none"
                                v-html="selected.content || '<p class=\'text-muted-foreground\'>This note is empty.</p>'"
                            ></div>
                        </div>
                        <div class="border-border border-t px-5 py-2.5 text-right">
                            <Link
                                :href="route('notes.show', selected.id)"
                                class="text-primary focus-visible:ring-ring/50 rounded-sm text-xs transition-colors duration-150 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                            >
                                Open this note in the editor
                            </Link>
                        </div>
                    </template>
                    <div v-else class="text-muted-foreground flex flex-1 items-center justify-center text-sm">Select a note to read</div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
