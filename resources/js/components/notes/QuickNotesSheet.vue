<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Search, Pin, FileText, ExternalLink, NotebookText } from 'lucide-vue-next';

const open = ref(false);
const notes = ref<any[]>([]);
const loaded = ref(false);
const loading = ref(false);
const query = ref('');
const selectedId = ref<number | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const fetchNotes = async () => {
    loading.value = true;
    try {
        const res = await fetch(route('notes.api.search'), { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            notes.value = data.notes || [];
            loaded.value = true;
        }
    } catch {
        /* non-fatal */
    } finally {
        loading.value = false;
    }
};

const openSheet = () => {
    open.value = true;
    if (!loaded.value) fetchNotes();
    nextTick(() => searchInput.value?.focus());
};

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return notes.value;
    return notes.value.filter(
        (n) => (n.title || '').toLowerCase().includes(q) || (n.content_preview || '').toLowerCase().includes(q),
    );
});

const pinned = computed(() => filtered.value.filter((n) => n.is_pinned));
const others = computed(() => filtered.value.filter((n) => !n.is_pinned));

const selected = computed(() => notes.value.find((n) => n.id === selectedId.value) || null);

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
        open.value ? (open.value = false) : openSheet();
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
            class="group fixed bottom-5 right-5 z-40 flex h-11 w-11 items-center justify-center rounded-xl border border-border bg-card text-foreground shadow-md ring-1 ring-black/5 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:text-primary hover:shadow-lg"
            title="Quick Notes (g then n)"
            aria-label="Open Quick Notes"
        >
            <NotebookText class="h-[18px] w-[18px]" />
            <span class="pointer-events-none absolute right-full mr-3 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background opacity-0 transition-opacity group-hover:opacity-100">
                Quick Notes
            </span>
        </button>
    </Teleport>

    <Sheet :open="open" @update:open="open = $event">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-3xl">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <FileText class="h-4 w-4 text-primary" />
                    <h2 class="font-display text-lg font-semibold tracking-tight text-foreground">Quick Notes</h2>
                    <span class="rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">read-only</span>
                </div>
                <Link
                    :href="route('notes.index')"
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-primary"
                >
                    Open editor <ExternalLink class="h-3.5 w-3.5" />
                </Link>
            </div>

            <div class="flex min-h-0 flex-1">
                <!-- List pane -->
                <div class="flex w-72 shrink-0 flex-col border-r border-border bg-muted/20">
                    <div class="border-b border-border p-3">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input ref="searchInput" v-model="query" placeholder="Search notes…" class="h-9 rounded-lg bg-card pl-8 text-sm" />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div v-if="loading && !loaded" class="px-4 py-8 text-center text-sm text-muted-foreground">Loading…</div>
                        <div v-else-if="!filtered.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                            {{ query ? 'No matches' : 'No notes yet' }}
                        </div>

                        <template v-else>
                            <p v-if="pinned.length" class="flex items-center gap-1.5 px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                <Pin class="h-3 w-3 text-amber-500" /> Pinned
                            </p>
                            <button
                                v-for="n in pinned"
                                :key="n.id"
                                @click="selectedId = n.id"
                                class="flex w-full flex-col gap-0.5 border-b border-l-2 border-b-border/60 px-3 py-2.5 text-left transition-colors"
                                :class="selectedId === n.id ? 'border-l-primary bg-accent' : 'border-l-transparent hover:bg-accent/50'"
                            >
                                <span class="truncate text-sm font-semibold text-foreground">{{ n.title || 'Untitled' }}</span>
                                <span class="line-clamp-1 text-xs text-muted-foreground">{{ n.content_preview }}</span>
                            </button>

                            <p v-if="pinned.length && others.length" class="px-3 pt-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">All notes</p>
                            <button
                                v-for="n in others"
                                :key="n.id"
                                @click="selectedId = n.id"
                                class="flex w-full flex-col gap-0.5 border-b border-l-2 border-b-border/60 px-3 py-2.5 text-left transition-colors"
                                :class="selectedId === n.id ? 'border-l-primary bg-accent' : 'border-l-transparent hover:bg-accent/50'"
                            >
                                <span class="truncate text-sm font-semibold text-foreground">{{ n.title || 'Untitled' }}</span>
                                <span class="line-clamp-1 text-xs text-muted-foreground">{{ n.content_preview }}</span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Reading pane -->
                <div class="flex min-w-0 flex-1 flex-col">
                    <template v-if="selected">
                        <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <Pin v-if="selected.is_pinned" class="h-3.5 w-3.5 shrink-0 text-amber-500" />
                                <h3 class="truncate font-display text-lg font-semibold tracking-tight text-foreground">{{ selected.title || 'Untitled' }}</h3>
                            </div>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatDate(selected.updated_at) }}</span>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                            <div class="tiptap-prose max-w-none" v-html="selected.content || '<p class=\'text-muted-foreground\'>This note is empty.</p>'"></div>
                        </div>
                        <div class="border-t border-border px-5 py-2.5 text-right">
                            <Link :href="route('notes.show', selected.id)" class="text-xs text-primary hover:underline">
                                Open this note in the editor →
                            </Link>
                        </div>
                    </template>
                    <div v-else class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Select a note to read
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
