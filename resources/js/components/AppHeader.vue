<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger, DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, NavItem } from '@/types';
import { Link, usePage, router } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CheckSquare,
    Calendar,
    Menu,
    Plus,
    Bell,
    Folder,
    Sun,
    Moon,
    FileText,
    AlertTriangle,
    Clock,
    CalendarDays,
    PenTool
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';

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

// Dark mode functionality
const isDark = ref(false);

onMounted(() => {
    // Check for saved theme preference or default to 'light'
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    isDark.value = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);
    updateTheme();
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    updateTheme();
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
};

const updateTheme = () => {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
};

const isCurrentRoute = computed(() => (url: string) => page.url === url);

const activeItemStyles = computed(
    () => (url: string) => (isCurrentRoute.value(url) ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' : ''),
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
];

// Handle creating new note
const createNewNote = () => {
    router.post(route('notes.create-empty'));
};
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80 bg-white dark:bg-neutral-900">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon class="size-6 fill-current text-black dark:text-white" />
                            </SheetHeader>
                            <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-accent"
                                        :class="activeItemStyles(item.href)"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="h-5 w-5" />
                                        {{ item.title }}
                                    </Link>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <div class="border-t pt-4">
                                        <Button class="w-full" size="sm" @click="createNewNote">
                                            <PenTool class="mr-2 h-4 w-4" />
                                            Add New Note
                                        </Button>
                                    </div>
                                    <div class="flex items-center justify-between border-t pt-4">
                                        <span class="text-sm font-medium">Theme</span>
                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="toggleTheme">
                                            <Sun v-if="isDark" class="h-4 w-4" />
                                            <Moon v-else class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="route('dashboard')" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList class="flex h-full items-stretch space-x-1">
                            <NavigationMenuItem v-for="(item, index) in mainNavItems" :key="index" class="relative flex h-full items-center">
                                <Link :href="item.href">
                                    <NavigationMenuLink
                                        :class="[navigationMenuTriggerStyle(), activeItemStyles(item.href), 'h-9 cursor-pointer px-3']"
                                    >
                                        <div class="flex items-center gap-2">
                                            <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                                            <span>{{ item.title }}</span>
                                        </div>
                                    </NavigationMenuLink>
                                </Link>
                                <div
                                    v-if="isCurrentRoute(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-primary"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <!-- Quick Actions -->
                    <div class="hidden items-center space-x-2 md:flex">
                        <Button size="sm" class="h-9" @click="createNewNote">
                            <PenTool class="mr-2 h-4 w-4" />
                            Add New Note
                        </Button>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center space-x-1">
                        <!-- Theme Toggle -->
                        <Button variant="ghost" size="icon" class="group h-9 w-9 cursor-pointer" @click="toggleTheme">
                            <Sun v-if="isDark" class="size-5 opacity-80 transition-opacity group-hover:opacity-100" />
                            <Moon v-else class="size-5 opacity-80 transition-opacity group-hover:opacity-100" />
                        </Button>

                        <!-- Notifications -->
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="ghost" size="icon" class="group relative h-9 w-9 cursor-pointer">
                                    <Bell class="size-5 opacity-80 transition-opacity group-hover:opacity-100" />
                                    <Badge
                                        v-if="props.notifications.total > 0"
                                        class="absolute -right-1 -top-1 h-5 w-5 rounded-full p-0 text-xs"
                                        :class="props.notifications.overdue_tasks > 0 ? 'bg-red-500 hover:bg-red-600' : 'bg-primary'"
                                    >
                                        {{ props.notifications.total > 9 ? '9+' : props.notifications.total }}
                                    </Badge>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-80" align="end">
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-medium">Notifications</h4>
                                        <Badge variant="secondary" class="text-xs">
                                            {{ props.notifications.total }}
                                        </Badge>
                                    </div>

                                    <div class="space-y-3">
                                        <!-- Overdue Tasks -->
                                        <div v-if="props.notifications.overdue_tasks > 0" class="flex items-center space-x-3 p-2 rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800">
                                            <AlertTriangle class="h-4 w-4 text-red-600 dark:text-red-400" />
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-red-900 dark:text-red-100">
                                                    {{ props.notifications.overdue_tasks }} Overdue Task{{ props.notifications.overdue_tasks > 1 ? 's' : '' }}
                                                </p>
                                                <p class="text-xs text-red-600 dark:text-red-400">Requires immediate attention</p>
                                            </div>
                                            <Button variant="ghost" size="sm" as-child>
                                                <Link :href="route('dashboard')" class="text-red-600 dark:text-red-400">
                                                    View
                                                </Link>
                                            </Button>
                                        </div>

                                        <!-- Due Soon Tasks -->
                                        <div v-if="props.notifications.due_soon_tasks > 0" class="flex items-center space-x-3 p-2 rounded-lg bg-orange-50 dark:bg-orange-950 border border-orange-200 dark:border-orange-800">
                                            <Clock class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-orange-900 dark:text-orange-100">
                                                    {{ props.notifications.due_soon_tasks }} Task{{ props.notifications.due_soon_tasks > 1 ? 's' : '' }} Due Soon
                                                </p>
                                                <p class="text-xs text-orange-600 dark:text-orange-400">Due within 7 days</p>
                                            </div>
                                            <Button variant="ghost" size="sm" as-child>
                                                <Link :href="route('dashboard')" class="text-orange-600 dark:text-orange-400">
                                                    View
                                                </Link>
                                            </Button>
                                        </div>

                                        <!-- Today's Events -->
                                        <div v-if="props.notifications.today_events > 0" class="flex items-center space-x-3 p-2 rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800">
                                            <CalendarDays class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                    {{ props.notifications.today_events }} Event{{ props.notifications.today_events > 1 ? 's' : '' }} Today
                                                </p>
                                                <p class="text-xs text-blue-600 dark:text-blue-400">Check your calendar</p>
                                            </div>
                                            <Button variant="ghost" size="sm" as-child>
                                                <Link :href="route('calendar.index')" class="text-blue-600 dark:text-blue-400">
                                                    View
                                                </Link>
                                            </Button>
                                        </div>

                                        <!-- No Notifications -->
                                        <div v-if="props.notifications.total === 0" class="text-center py-6 text-gray-500 dark:text-gray-400">
                                            <Bell class="h-8 w-8 mx-auto mb-2 opacity-50" />
                                            <p class="text-sm">No new notifications</p>
                                        </div>
                                    </div>
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <!-- User Menu -->
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 transition-all focus-within:ring-2 focus-within:ring-primary hover:bg-accent"
                            >
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                                    <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
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

        <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70 bg-neutral-50/50 dark:bg-neutral-900/50">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
