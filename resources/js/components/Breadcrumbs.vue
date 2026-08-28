<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList class="text-xs">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage class="font-medium">{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink v-if="item.href" as-child>
                            <Link
                                :href="item.href"
                                class="hover:text-foreground focus-visible:ring-ring/50 rounded-sm transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                            >
                                {{ item.title }}
                            </Link>
                        </BreadcrumbLink>
                        <!-- No href: plain text, never a focusable dead link -->
                        <span v-else>{{ item.title }}</span>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
