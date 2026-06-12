<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';
import { Toaster } from '@/components/ui/sonner'

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    fullBleed?: boolean;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    fullBleed: false,
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />
        <AppContent :full-bleed="fullBleed">
            <slot />
        </AppContent>
        <Toaster position="top-right" />
    </AppShell>
</template>
