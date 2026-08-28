<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    notifications?: {
        total: number;
        overdue_tasks: number;
        due_soon_tasks: number;
        today_events: number;
    };
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    notifications: () => ({
        total: 0,
        overdue_tasks: 0,
        due_soon_tasks: 0,
        today_events: 0,
    }),
});
</script>

<template>
    <AppShell variant="sidebar">
        <a
            href="#main-content"
            class="focus:bg-primary focus:text-primary-foreground sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-[100] focus:rounded-md focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:outline-none"
        >
            Skip to content
        </a>
        <AppSidebar />
        <AppContent variant="sidebar" id="main-content" tabindex="-1" class="outline-none">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" :notifications="notifications" />
            <slot />
        </AppContent>
        <Toaster position="top-right" />
    </AppShell>
</template>
