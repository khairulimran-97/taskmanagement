<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import QuickNotesSheet from '@/components/notes/QuickNotesSheet.vue';
import QuickVaultSheet from '@/components/secrets/QuickVaultSheet.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bell,
    Calendar,
    CalendarDays,
    ChevronRight,
    Clock,
    FileText,
    Folder,
    KeyRound,
    LayoutDashboard,
    Loader2,
    Menu,
    Monitor,
    Moon,
    PenTool,
    Sun,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const quickNotes = ref<InstanceType<typeof QuickNotesSheet> | null>(null);

interface Props {
    breadcrumbs?: BreadcrumbItem[];
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

const page = usePage();
const auth = computed(() => page.props.auth);

// Theme — single source of truth via useAppearance (localStorage 'appearance').
// Cycles light → dark → system so the system preference stays reachable.
const { appearance, updateAppearance } = useAppearance();

const nextTheme = { light: 'dark', dark: 'system', system: 'light' } as const;
const themeIcon = computed(() => ({ light: Sun, dark: Moon, system: Monitor })[appearance.value]);
const themeLabel = computed(() => ({ light: 'Light', dark: 'Dark', system: 'System' })[appearance.value]);

const cycleTheme = () => {
    updateAppearance(nextTheme[appearance.value]);
};

const isCurrentRoute = computed(() => (url: string) => {
    const path = page.url.split('?')[0];
    return path === url || path.startsWith(url + '/');
});

const mobileNavItemStyles = computed(
    () => (url: string) => (isCurrentRoute.value(url) ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'),
);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Projects',
        href: '/projects',
        icon: Folder,
    },
    {
        title: 'Notes',
        href: '/notes',
        icon: FileText,
    },
    {
        title: 'Calendar',
        href: '/calendar',
        icon: Calendar,
    },
    {
        title: 'Vault',
        href: '/secrets',
        icon: KeyRound,
    },
];

// Controlled so the sheet closes on in-sheet navigation instead of relying on a remount
const mobileMenuOpen = ref(false);

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
                mobileMenuOpen.value = false;
            },
        },
    );
};
</script>

<template>
    <div class="contents">
        <div class="border-border bg-background sticky top-0 z-50 border-b">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile menu -->
                <div class="lg:hidden">
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="text-muted-foreground hover:text-foreground mr-2 size-10"
                                aria-label="Open menu"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">Navigation menu</SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogo />
                            </SheetHeader>
                            <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        prefetch
                                        class="focus-visible:ring-ring/50 flex min-h-11 items-center gap-x-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                                        :class="mobileNavItemStyles(item.href)"
                                        :aria-current="isCurrentRoute(item.href) ? 'page' : undefined"
                                        @click="mobileMenuOpen = false"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="size-4" />
                                        {{ item.title }}
                                    </Link>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <div class="border-border border-t pt-4">
                                        <Button class="w-full" size="sm" :disabled="creatingNote" @click="createNewNote">
                                            <Loader2 v-if="creatingNote" class="size-4 animate-spin" />
                                            <PenTool v-else class="size-4" />
                                            New note
                                        </Button>
                                    </div>
                                    <div class="border-border flex items-center justify-between border-t pt-4">
                                        <span class="text-sm font-medium">Theme</span>
                                        <Button variant="outline" size="sm" class="min-w-24" @click="cycleTheme">
                                            <component :is="themeIcon" class="size-4" />
                                            {{ themeLabel }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link
                    :href="route('dashboard')"
                    class="focus-visible:ring-ring/50 flex items-center gap-x-2 rounded-md focus-visible:ring-2 focus-visible:outline-none"
                >
                    <AppLogo />
                </Link>

                <!-- Desktop nav -->
                <nav class="ml-8 hidden h-full items-stretch gap-1 lg:flex" aria-label="Main">
                    <Link
                        v-for="item in mainNavItems"
                        :key="item.title"
                        :href="item.href"
                        prefetch
                        class="focus-visible:ring-ring/50 relative flex items-center gap-2 rounded-md px-3 text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        :class="isCurrentRoute(item.href) ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                        :aria-current="isCurrentRoute(item.href) ? 'page' : undefined"
                    >
                        <component v-if="item.icon" :is="item.icon" class="size-4" />
                        <span>{{ item.title }}</span>
                        <span
                            class="bg-primary absolute inset-x-3 bottom-0 h-0.5 rounded-full transition-opacity duration-150"
                            :class="isCurrentRoute(item.href) ? 'opacity-100' : 'opacity-0'"
                            aria-hidden="true"
                        ></span>
                    </Link>
                </nav>

                <div class="ml-auto flex items-center space-x-2">
                    <!-- Quick actions -->
                    <div class="hidden items-center md:flex">
                        <Button variant="outline" size="sm" class="h-9" :disabled="creatingNote" @click="createNewNote">
                            <Loader2 v-if="creatingNote" class="size-4 animate-spin" />
                            <PenTool v-else class="size-4" />
                            New note
                        </Button>
                    </div>

                    <div class="flex items-center space-x-1">
                        <!-- Theme toggle: light → dark → system -->
                        <TooltipProvider :delay-duration="300">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground size-10"
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
                                    class="text-muted-foreground hover:text-foreground relative size-10"
                                    :aria-label="`Notifications, ${props.notifications.total} new`"
                                >
                                    <Bell class="size-5" />
                                    <span
                                        v-if="props.notifications.total > 0"
                                        class="absolute top-0 right-0 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs leading-none font-medium tabular-nums"
                                        :class="
                                            props.notifications.overdue_tasks > 0 ? 'bg-destructive text-white' : 'bg-primary text-primary-foreground'
                                        "
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
                                                {{ props.notifications.overdue_tasks }} overdue
                                                {{ props.notifications.overdue_tasks > 1 ? 'tasks' : 'task' }}
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
                                                {{ props.notifications.due_soon_tasks }}
                                                {{ props.notifications.due_soon_tasks > 1 ? 'tasks' : 'task' }} due soon
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
                                                {{ props.notifications.today_events }}
                                                {{ props.notifications.today_events > 1 ? 'events' : 'event' }} today
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

                    <!-- User menu -->
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="size-10 rounded-full" aria-label="Open user menu">
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                    <AvatarFallback class="bg-primary/10 text-primary rounded-full font-medium">
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <!-- Breadcrumb strip: rendered for any breadcrumbs so its height stays stable between routes -->
        <div v-if="props.breadcrumbs.length > 0" class="border-border bg-muted/30 flex w-full border-b">
            <div class="text-muted-foreground mx-auto flex h-10 w-full items-center justify-start px-4 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>

        <!-- Global quick notes (read-only) -->
        <QuickNotesSheet ref="quickNotes" />

        <!-- Global quick vault (read-only) -->
        <QuickVaultSheet />
    </div>
</template>
