<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import QuickNotesSheet from '@/components/notes/QuickNotesSheet.vue';
import QuickVaultSheet from '@/components/secrets/QuickVaultSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Bell, CalendarDays, ChevronRight, Clock, Loader2, Monitor, Moon, PenTool, Sun } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    notifications?: {
        total: number;
        overdue_tasks: number;
        due_soon_tasks: number;
        today_events: number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    notifications: () => ({
        total: 0,
        overdue_tasks: 0,
        due_soon_tasks: 0,
        today_events: 0,
    }),
});

// Theme — single source of truth via useAppearance (localStorage 'appearance').
// Cycles light → dark → system so the system preference stays reachable.
const { appearance, updateAppearance } = useAppearance();

const nextTheme = { light: 'dark', dark: 'system', system: 'light' } as const;
const themeIcon = computed(() => ({ light: Sun, dark: Moon, system: Monitor })[appearance.value]);
const themeLabel = computed(() => ({ light: 'Light', dark: 'Dark', system: 'System' })[appearance.value]);

const cycleTheme = () => {
    updateAppearance(nextTheme[appearance.value]);
};

// Guarded so a double-click cannot create two notes
const creatingNote = ref(false);
const createNewNote = () => {
    if (creatingNote.value) return;
    creatingNote.value = true;
    router.post(
        route('notes.create-empty'),
        {},
        {
            onError: () => toast.error('Could not create note'),
            onFinish: () => {
                creatingNote.value = false;
            },
        },
    );
};
</script>

<template>
    <header class="border-border bg-background sticky top-0 z-40 flex h-14 shrink-0 items-center gap-2 border-b px-4">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="text-muted-foreground hover:text-foreground -ml-1.5 size-9" />
            <Separator orientation="vertical" class="mr-1 h-4!" />
            <Breadcrumbs v-if="props.breadcrumbs.length > 0" :breadcrumbs="props.breadcrumbs" />
        </div>

        <div class="flex items-center gap-1">
            <!-- Quick note -->
            <Button variant="outline" size="sm" class="hidden sm:inline-flex" :disabled="creatingNote" @click="createNewNote">
                <Loader2 v-if="creatingNote" class="size-4 animate-spin" />
                <PenTool v-else class="size-4" />
                New note
            </Button>
            <Button
                variant="ghost"
                size="icon"
                class="text-muted-foreground hover:text-foreground size-9 sm:hidden"
                aria-label="New note"
                :disabled="creatingNote"
                @click="createNewNote"
            >
                <Loader2 v-if="creatingNote" class="size-5 animate-spin" />
                <PenTool v-else class="size-5" />
            </Button>

            <!-- Theme toggle: light → dark → system -->
            <TooltipProvider :delay-duration="300">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-foreground size-9"
                            :aria-label="`Switch theme, currently ${themeLabel.toLowerCase()}`"
                            @click="cycleTheme"
                        >
                            <component :is="themeIcon" class="size-5" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Theme: {{ themeLabel }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <!-- Notifications -->
            <Popover>
                <PopoverTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-foreground relative size-9"
                        :aria-label="`Notifications, ${props.notifications.total} new`"
                    >
                        <Bell class="size-5" />
                        <span
                            v-if="props.notifications.total > 0"
                            class="absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-none font-medium tabular-nums"
                            :class="props.notifications.overdue_tasks > 0 ? 'bg-destructive text-white' : 'bg-primary text-primary-foreground'"
                        >
                            {{ props.notifications.total > 9 ? '9+' : props.notifications.total }}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent class="w-80 p-2" align="end">
                    <div class="flex items-center justify-between px-2 py-1.5">
                        <h4 class="text-sm font-semibold">Notifications</h4>
                        <Badge variant="secondary" class="text-xs tabular-nums">
                            {{ props.notifications.total }}
                        </Badge>
                    </div>

                    <div v-if="props.notifications.total > 0" class="mt-1 space-y-1">
                        <!-- Overdue tasks -->
                        <Link
                            v-if="props.notifications.overdue_tasks > 0"
                            :href="route('dashboard')"
                            class="hover:bg-muted/60 focus-visible:ring-ring/50 flex min-h-11 items-center gap-3 rounded-md px-2 py-2 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <span class="bg-destructive/10 text-destructive flex size-8 shrink-0 items-center justify-center rounded-md">
                                <AlertTriangle class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium tabular-nums">
                                    {{ props.notifications.overdue_tasks }} overdue {{ props.notifications.overdue_tasks > 1 ? 'tasks' : 'task' }}
                                </span>
                                <span class="text-muted-foreground block text-xs">Needs attention</span>
                            </span>
                            <ChevronRight class="text-muted-foreground size-4 shrink-0" />
                        </Link>

                        <!-- Due soon tasks -->
                        <Link
                            v-if="props.notifications.due_soon_tasks > 0"
                            :href="route('dashboard')"
                            class="hover:bg-muted/60 focus-visible:ring-ring/50 flex min-h-11 items-center gap-3 rounded-md px-2 py-2 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <span class="bg-warning/10 text-warning flex size-8 shrink-0 items-center justify-center rounded-md">
                                <Clock class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium tabular-nums">
                                    {{ props.notifications.due_soon_tasks }} {{ props.notifications.due_soon_tasks > 1 ? 'tasks' : 'task' }} due soon
                                </span>
                                <span class="text-muted-foreground block text-xs">Due within 7 days</span>
                            </span>
                            <ChevronRight class="text-muted-foreground size-4 shrink-0" />
                        </Link>

                        <!-- Today's events -->
                        <Link
                            v-if="props.notifications.today_events > 0"
                            :href="route('calendar.index')"
                            class="hover:bg-muted/60 focus-visible:ring-ring/50 flex min-h-11 items-center gap-3 rounded-md px-2 py-2 transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        >
                            <span class="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-md">
                                <CalendarDays class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium tabular-nums">
                                    {{ props.notifications.today_events }} {{ props.notifications.today_events > 1 ? 'events' : 'event' }} today
                                </span>
                                <span class="text-muted-foreground block text-xs">On your calendar</span>
                            </span>
                            <ChevronRight class="text-muted-foreground size-4 shrink-0" />
                        </Link>
                    </div>

                    <!-- No notifications -->
                    <div v-else class="px-2 py-6 text-center">
                        <Bell class="text-muted-foreground/50 mx-auto mb-2 size-8" />
                        <p class="text-muted-foreground text-sm">No new notifications</p>
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    </header>

    <!-- Global quick notes (read-only) -->
    <QuickNotesSheet />

    <!-- Global quick vault (read-only) -->
    <QuickVaultSheet />
</template>
