<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CheckSquare,
    Calendar,
    Menu,
    Plus,
    Bell,
    Folder,
    Sun,
    Moon
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
    notifications?: number;
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    notifications: 0,
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
        title: 'Calendar',
        href: '/calendar',
        icon: Calendar,
    },
];
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
                                        <Button class="w-full" size="sm">
                                            <Plus class="mr-2 h-4 w-4" />
                                            New Task
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
                        <Button size="sm" class="h-9">
                            <Plus class="mr-2 h-4 w-4" />
                            New Task
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
                        <Button variant="ghost" size="icon" class="group relative h-9 w-9 cursor-pointer">
                            <Bell class="size-5 opacity-80 transition-opacity group-hover:opacity-100" />
                            <Badge
                                v-if="props.notifications > 0"
                                class="absolute -right-1 -top-1 h-5 w-5 rounded-full p-0 text-xs"
                            >
                                {{ props.notifications > 9 ? '9+' : props.notifications }}
                            </Badge>
                        </Button>
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
