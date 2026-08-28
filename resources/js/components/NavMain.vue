<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage<SharedData>();

// Prefix match so sub-pages (/projects/3, /notes/…) keep their section active
const isCurrent = computed(() => (href: string) => {
    const path = page.url.split('?')[0];
    return path === href || path.startsWith(href + '/');
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label" class="text-sidebar-foreground/60 text-[11px] font-medium tracking-wide uppercase">
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="isCurrent(item.href)" :tooltip="item.title">
                    <Link :href="item.href" prefetch :aria-current="isCurrent(item.href) ? 'page' : undefined">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
