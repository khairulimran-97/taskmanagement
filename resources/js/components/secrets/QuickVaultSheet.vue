<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Search, KeyRound, ChevronLeft, Eye, EyeOff, Copy, Check, StickyNote, Lock } from 'lucide-vue-next';
import { secretTypeBadge, secretTypeLabel, secretTypeDot } from '@/lib/secretMeta';
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
const query = ref('');
const selectedId = ref<number | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

// Reveal / copy state for the reading pane
const valueRevealed = ref(false);
const copied = ref(false);

const fetchSecrets = async () => {
    loading.value = true;
    try {
        const res = await fetch(route('secrets.api.list'), { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            secrets.value = data.secrets || [];
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
    mobileReading.value = false;
    if (!loaded.value) fetchSecrets();
    nextTick(() => searchInput.value?.focus());
};

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return secrets.value;
    return secrets.value.filter(
        (s) => (s.name || '').toLowerCase().includes(q) || secretTypeLabel(s.type).toLowerCase().includes(q),
    );
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

// Auto-select first secret when opening / filtering
watch([open, filtered], () => {
    if (open.value && filtered.value.length && !filtered.value.some((s) => s.id === selectedId.value)) {
        selectedId.value = filtered.value[0].id;
    }
});

const copyValue = async (secret: Secret) => {
    try {
        await navigator.clipboard.writeText(secret.value);
        copied.value = true;
        toast.success('Secret copied to clipboard');
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
    <!-- Floating launcher (sits above the Quick Notes button) -->
    <Teleport to="body">
        <button
            v-show="!open"
            @click="openSheet"
            class="group fixed bottom-[4.5rem] right-5 z-40 flex h-11 w-11 items-center justify-center rounded-xl border border-border bg-card text-foreground shadow-md ring-1 ring-black/5 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:text-primary hover:shadow-lg"
            title="Quick Vault (g then v)"
            aria-label="Open Quick Vault"
        >
            <KeyRound class="h-[18px] w-[18px]" />
            <span class="pointer-events-none absolute right-full mr-3 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background opacity-0 transition-opacity group-hover:opacity-100">
                Quick Vault
            </span>
        </button>
    </Teleport>

    <Sheet :open="open" @update:open="open = $event">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:!max-w-4xl lg:!max-w-5xl">
            <!-- Header -->
            <div class="flex items-center gap-2 border-b border-border px-5 py-3.5">
                <KeyRound class="h-4 w-4 text-primary" />
                <h2 class="font-display text-lg font-semibold tracking-tight text-foreground">Quick Vault</h2>
                <span class="rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">read-only</span>
            </div>

            <div class="flex min-h-0 flex-1">
                <!-- List pane -->
                <div
                    class="flex w-full shrink-0 flex-col border-r border-border bg-muted/20 md:w-80"
                    :class="{ 'hidden md:flex': mobileReading }"
                >
                    <div class="border-b border-border p-3">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input ref="searchInput" v-model="query" placeholder="Search secrets…" class="h-9 rounded-lg bg-card pl-8 text-sm" />
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div v-if="loading && !loaded" class="px-4 py-8 text-center text-sm text-muted-foreground">Loading…</div>
                        <div v-else-if="!filtered.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                            {{ query ? 'No matches' : 'No secrets yet' }}
                        </div>

                        <template v-else>
                            <button
                                v-for="s in filtered"
                                :key="s.id"
                                @click="selectSecret(s.id)"
                                class="relative flex w-full items-center gap-2.5 border-b border-border/60 py-2.5 pl-4 pr-3 text-left transition-colors hover:bg-muted/50"
                                :class="selectedId === s.id ? 'bg-primary/8' : ''"
                            >
                                <span
                                    v-if="selectedId === s.id"
                                    class="absolute inset-y-0 left-0 w-0.5 bg-primary"
                                ></span>
                                <span class="h-2 w-2 shrink-0 rounded-full" :class="secretTypeDot(s.type)" :title="secretTypeLabel(s.type)"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-foreground">{{ s.name }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ secretTypeLabel(s.type) }}</span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Reading pane -->
                <div
                    class="flex min-w-0 flex-1 flex-col"
                    :class="{ 'hidden md:flex': !mobileReading }"
                >
                    <template v-if="selected">
                        <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <button
                                    class="-ml-1 mr-0.5 shrink-0 rounded p-1 text-muted-foreground hover:text-foreground md:hidden"
                                    @click="mobileReading = false"
                                    aria-label="Back to list"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </button>
                                <h3 class="truncate font-display text-lg font-semibold tracking-tight text-foreground">{{ selected.name }}</h3>
                                <Badge variant="outline" :class="secretTypeBadge(selected.type)" class="shrink-0 text-[11px] font-medium">
                                    {{ secretTypeLabel(selected.type) }}
                                </Badge>
                            </div>
                            <span class="shrink-0 text-xs text-muted-foreground">{{ formatDate(selected.updated_at) }}</span>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-6">
                            <!-- Secret value -->
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <Lock class="h-3.5 w-3.5 text-muted-foreground" />
                                        <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Secret</span>
                                    </div>
                                    <span v-if="valueRevealed" class="text-[11px] tabular-nums text-muted-foreground">
                                        {{ charCount }} chars<template v-if="lineCount > 1"> · {{ lineCount }} lines</template>
                                    </span>
                                </div>

                                <!-- Framed secret block -->
                                <div class="overflow-hidden rounded-xl border border-border bg-muted/40">
                                    <!-- Masked: whole block is a reveal CTA -->
                                    <button
                                        v-if="!valueRevealed"
                                        type="button"
                                        @click="valueRevealed = true"
                                        class="group flex w-full items-center justify-between gap-3 px-4 py-4 text-left transition-colors hover:bg-muted/70"
                                    >
                                        <span class="font-mono text-base tracking-[0.3em] text-muted-foreground select-none">••••••••••••</span>
                                        <span class="flex shrink-0 items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors group-hover:text-primary">
                                            <Eye class="h-4 w-4" />
                                            Click to reveal
                                        </span>
                                    </button>

                                    <!-- Revealed -->
                                    <div v-else>
                                        <pre class="max-h-72 overflow-auto px-4 py-3.5 font-mono text-[13px] leading-relaxed whitespace-pre-wrap break-all text-foreground">{{ selected.value }}</pre>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-8"
                                        @click="copyValue(selected)"
                                    >
                                        <Check v-if="copied" class="mr-1.5 h-4 w-4 text-emerald-500" />
                                        <Copy v-else class="mr-1.5 h-4 w-4" />
                                        {{ copied ? 'Copied' : 'Copy' }}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 text-muted-foreground"
                                        @click="valueRevealed = !valueRevealed"
                                    >
                                        <EyeOff v-if="valueRevealed" class="mr-1.5 h-4 w-4" />
                                        <Eye v-else class="mr-1.5 h-4 w-4" />
                                        {{ valueRevealed ? 'Hide' : 'Reveal' }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div v-if="selected.notes" class="space-y-2">
                                <div class="flex items-center gap-1.5">
                                    <StickyNote class="h-3.5 w-3.5 text-muted-foreground" />
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Notes</span>
                                </div>
                                <p class="whitespace-pre-wrap rounded-lg border border-border bg-card px-3.5 py-3 text-sm text-foreground">{{ selected.notes }}</p>
                            </div>

                        </div>

                        <div class="border-t border-border px-5 py-2.5 text-right">
                            <Link :href="route('secrets.index')" class="text-xs text-primary hover:underline">
                                Open the full vault →
                            </Link>
                        </div>
                    </template>
                    <div v-else class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Select a secret to view
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
