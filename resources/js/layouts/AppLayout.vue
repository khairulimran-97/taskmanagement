<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    fullBleed?: boolean;
    notifications?: {
        total: number;
        overdue_tasks: number;
        due_soon_tasks: number;
        today_events: number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    fullBleed: false,
    notifications: () => ({
        total: 0,
        overdue_tasks: 0,
        due_soon_tasks: 0,
        today_events: 0,
    }),
});
</script>

<template>
    <AppHeaderLayout :breadcrumbs="breadcrumbs" :full-bleed="fullBleed">
        <slot />
    </AppHeaderLayout>
</template>
