<script setup lang="ts">
import AppHeaderLayout from '@/layouts/app/AppHeaderLayout.vue';
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

const props = withDefaults(defineProps<Props>(), {
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
    <AppHeaderLayout :breadcrumbs="breadcrumbs" :notifications="notifications">
        <slot />
    </AppHeaderLayout>
</template>
