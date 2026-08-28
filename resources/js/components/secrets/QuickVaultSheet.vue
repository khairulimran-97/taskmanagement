<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { secretTypeLabel } from '@/lib/secretMeta';
import { Link } from '@inertiajs/vue3';
import { Check, ChevronLeft, Copy, Eye, EyeOff, KeyRound, Lock, RefreshCw, Search, StickyNote } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Secret {
    id: number;
    name: string;
    type: string;
    value: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

const open = ref(false);
const secrets = ref<Secret[]>([]);
const loaded = ref(false);
const loading = ref(false);
const loadError = ref(false);
const query = ref('');
const selectedId = ref<number | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

// Reveal / copy state for the reading pane
const valueRevealed = ref(false);
const copied = ref(false);

const fetchSecrets = async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const res = await fetch(route('secrets.api.list'), { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        secrets.value = data.secrets || [];
        loaded.value = true;
    } catch {
        // A failed refresh keeps the current list; a failed first load gets a retry state
        if (loaded.value) {
            toast.error('Could not refresh secrets');
        } else {
            loadError.value = true;
        }
    } finally {
        loading.value = false;
    }
};

const openSheet = () => {
    open.value = true;
    mobileReading.value = false;
    if (!loaded.value) fetchSecrets();
    nextTick(() => searchInput.value?.focus());
};

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return secrets.value;
    return secrets.value.filter((s) => (s.name || '').toLowerCase().includes(q) || secretTypeLabel(s.type).toLowerCase().includes(q));
});

const selected = computed(() => secrets.value.find((s) => s.id === selectedId.value) || null);

// Mobile is single-pane: tapping a secret shows the reading view
const mobileReading = ref(false);
const selectSecret = (id: number) => {
    selectedId.value = id;
    mobileReading.value = true;
};

// Reset reveal/copy whenever the selection changes
watch(selectedId, () => {
    valueRevealed.value = false;
    copied.value = false;
});

// Never auto-select on the user's behalf; only drop a selection the filter removed
watch(filtered, (list) => {
    if (selectedId.value !== null && !list.some((s) => s.id === selectedId.value)) {
        selectedId.value = null;
        mobileReading.value = false;
    }
});

// Arrow-key navigation through the filtered list (from the search field or a row)
const moveSelection = (delta: number) => {
    const list = filtered.value;
    if (!list.length) return;
    const idx = list.findIndex((s) => s.id === selectedId.value);
    const next = idx === -1 ? (delta > 0 ? 0 : list.length - 1) : Math.min(Math.max(idx + delta, 0), list.length - 1);
    selectedId.value = list[next].id;
};

const copyValue = async (secret: Secret) => {
    try {
        await navigator.clipboard.writeText(secret.value);
        copied.value = true;
        toast.success('Copied');
        window.setTimeout(() => (copied.value = false), 1500);
    } catch {
        toast.error('Could not copy to clipboard');
    }
};

const formatDate = (s: string) => {
    if (!s) return '';
    const d = new Date(s);
    const diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 3600) return `${Math.max(1, Math.floor(diff / 60))}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Lightweight stats for the revealed secret
const charCount = computed(() => selected.value?.value.length ?? 0);
const lineCount = computed(() => (selected.value?.value.match(/\n/g)?.length ?? 0) + 1);

// Keyboard: g then v, or Cmd/Ctrl+Shift+K to open; Esc closes
let gPressed = false;
let gTimer: ReturnType<typeof setTimeout> | null = null;
const onKey = (e: KeyboardEvent) => {
    const t = e.target as HTMLElement;
    const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);

    if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key.toLowerCase() === 'k') {
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
    if (gPressed && e.key.toLowerCase() === 'v') {
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
    <TooltipProvider :delay-duration="300">
        <!-- Floating launcher (sits above the Quick Notes button) -->
        <Teleport to="body">
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        v-show="!open"
                        type="button"
                        class="border-border bg-card text-muted-foreground hover:border-muted-foreground/30 hover:text-primary focus-visible:ring-ring/50 fixed right-5 bottom-[4.5rem] z-40 flex size-11 cursor-pointer items-center justify-center rounded-lg border shadow-md transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        aria-label="Open quick vault"
                        @click="openSheet"
                    >
                        <KeyRound class="size-5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent side="left">Quick vault (G then V)</TooltipContent>
            </Tooltip>
        </Teleport>

        <Sheet :open="open" @update:open="open = $event">
            <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:!max-w-4xl lg:!max-w-5xl">
                <!-- Header -->
                <div class="border-border flex items-center gap-2 border-b py-3 pr-12 pl-5">
                    <KeyRound class="text-primary size-4" />
                    <h2 class="text-foreground text-base font-semibold tracking-tight">Quick vault</h2>
                    <span class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium">View only</span>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-muted-foreground hidden text-xs lg:inline">G then V</span>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground hover:text-foreground size-8"
                                    aria-label="Refresh secrets"
                                    :disabled="loading"
                                    @click="fetchSecrets"
                                >
                                    <RefreshCw class="size-4" :class="loading ? 'animate-spin' : ''" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Refresh</TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1">
                    <!-- List pane -->
                    <div
                        class="border-border bg-muted/20 flex w-full shrink-0 flex-col border-r md:w-80"
                        :class="{ 'hidden md:flex': mobileReading }"
                    >
                        <div class="border-border border-b p-3">
                            <div class="relative">
                                <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                                <Input
                                    ref="searchInput"
                                    v-model="query"
                                    placeholder="Search secrets…"
                                    class="bg-card h-9 pl-8 text-sm"
                                    @keydown.down.prevent="moveSelection(1)"
                                    @keydown.up.prevent="moveSelection(-1)"
                                />
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <div v-if="loading && !loaded" class="space-y-4 p-4" aria-hidden="true">
                                <div v-for="i in 5" :key="i" class="space-y-1.5">
                                    <Skeleton class="h-3.5 w-3/4" />
                                    <Skeleton class="h-3 w-1/2" />
                                </div>
                            </div>

                            <div v-else-if="loadError && !loaded" class="p-3">
                                <EmptyState :icon="KeyRound" title="Couldn't load secrets" description="Check your connection and try again.">
                                    <template #action>
                                        <Button variant="outline" size="sm" @click="fetchSecrets">Try again</Button>
                                    </template>
                                </EmptyState>
                            </div>

                            <div v-else-if="!filtered.length" class="p-3">
                                <EmptyState v-if="query" :icon="Search" title="No matches" description="No secrets match your search.">
                                    <template #action>
                                        <Button variant="ghost" size="sm" @click="query = ''">Clear search</Button>
                                    </template>
                                </EmptyState>
                                <EmptyState v-else :icon="KeyRound" title="No secrets yet" description="Secrets you add to the vault appear here.">
                                    <template #action>
                                        <Button variant="outline" size="sm" as-child>
                                            <Link :href="route('secrets.index')">Open the vault</Link>
                                        </Button>
                                    </template>
                                </EmptyState>
                            </div>

                            <template v-else>
                                <button
                                    v-for="s in filtered"
                                    :key="s.id"
                                    type="button"
                                    class="border-border/60 focus-visible:ring-ring/50 flex w-full cursor-pointer items-center gap-2.5 border-b px-4 py-2.5 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                    :class="selectedId === s.id ? 'bg-muted' : 'hover:bg-muted/60'"
                                    :aria-pressed="selectedId === s.id"
                                    @click="selectSecret(s.id)"
                                    @keydown.down.prevent="moveSelection(1)"
                                    @keydown.up.prevent="moveSelection(-1)"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span class="text-foreground block truncate text-sm font-medium">{{ s.name }}</span>
                                        <span class="text-muted-foreground block truncate text-xs">{{ secretTypeLabel(s.type) }}</span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Reading pane -->
                    <div class="flex min-w-0 flex-1 flex-col" :class="{ 'hidden md:flex': !mobileReading }">
                        <template v-if="selected">
                            <div class="border-border flex items-center justify-between gap-3 border-b px-5 py-3">
                                <div class="flex min-w-0 items-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground -ml-1 size-8 shrink-0 md:hidden"
                                        aria-label="Back to list"
                                        @click="mobileReading = false"
                                    >
                                        <ChevronLeft class="size-4" />
                                    </Button>
                                    <h3 class="text-foreground truncate text-base font-semibold tracking-tight">{{ selected.name }}</h3>
                                    <Badge variant="outline" class="text-muted-foreground shrink-0 font-normal">
                                        {{ secretTypeLabel(selected.type) }}
                                    </Badge>
                                </div>
                                <span class="text-muted-foreground shrink-0 text-xs tabular-nums">{{ formatDate(selected.updated_at) }}</span>
                            </div>

                            <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                                <!-- Secret value -->
                                <div class="space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <Lock class="text-muted-foreground size-3.5" />
                                            <span class="text-muted-foreground text-xs font-medium">Secret</span>
                                        </div>
                                        <span v-if="valueRevealed" class="text-muted-foreground text-xs tabular-nums">
                                            {{ charCount }} chars<template v-if="lineCount > 1"> · {{ lineCount }} lines</template>
                                        </span>
                                    </div>

                                    <!-- Framed secret block -->
                                    <div class="border-border bg-muted/40 overflow-hidden rounded-lg border">
                                        <Transition name="fade" mode="out-in">
                                            <!-- Masked: whole block is a reveal CTA -->
                                            <button
                                                v-if="!valueRevealed"
                                                type="button"
                                                class="group hover:bg-muted/70 focus-visible:ring-ring/50 flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-4 text-left transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                                aria-label="Reveal value"
                                                :aria-pressed="false"
                                                @click="valueRevealed = true"
                                            >
                                                <span class="text-muted-foreground font-mono text-sm tracking-widest select-none">••••••••••••</span>
                                                <span
                                                    class="text-muted-foreground group-hover:text-primary flex shrink-0 items-center gap-1.5 text-xs font-medium transition-colors duration-150"
                                                >
                                                    <Eye class="size-4" />
                                                    Click to reveal
                                                </span>
                                            </button>

                                            <!-- Revealed -->
                                            <pre
                                                v-else
                                                class="text-foreground max-h-72 overflow-auto px-4 py-3.5 font-mono text-sm leading-relaxed break-all whitespace-pre-wrap"
                                                >{{ selected.value }}</pre
                                            >
                                        </Transition>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-2">
                                        <Button variant="outline" size="sm" @click="copyValue(selected)">
                                            <Check v-if="copied" class="text-success size-4" />
                                            <Copy v-else class="size-4" />
                                            {{ copied ? 'Copied' : 'Copy' }}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground"
                                            :aria-pressed="valueRevealed"
                                            @click="valueRevealed = !valueRevealed"
                                        >
                                            <EyeOff v-if="valueRevealed" class="size-4" />
                                            <Eye v-else class="size-4" />
                                            {{ valueRevealed ? 'Hide' : 'Reveal' }}
                                        </Button>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div v-if="selected.notes" class="space-y-2">
                                    <div class="flex items-center gap-1.5">
                                        <StickyNote class="text-muted-foreground size-3.5" />
                                        <span class="text-muted-foreground text-xs font-medium">Notes</span>
                                    </div>
                                    <p class="border-border bg-card text-foreground rounded-lg border px-3.5 py-3 text-sm whitespace-pre-wrap">
                                        {{ selected.notes }}
                                    </p>
                                </div>
                            </div>

                            <div class="border-border border-t px-5 py-2.5 text-right">
                                <Link
                                    :href="route('secrets.index')"
                                    class="text-primary focus-visible:ring-ring/50 rounded-sm text-xs underline-offset-4 transition-colors duration-150 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    Open the full vault →
                                </Link>
                            </div>
                        </template>
                        <div v-else class="text-muted-foreground flex flex-1 items-center justify-center px-6 text-center text-sm">
                            Select a secret to view it here
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </TooltipProvider>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
