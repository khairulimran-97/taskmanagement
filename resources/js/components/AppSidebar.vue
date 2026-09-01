<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Calendar, FileText, Folder, KeyRound, LayoutDashboard, Plug, Settings } from 'lucide-vue-next';

const overviewItems: NavItem[] = [{ title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard }];

const workspaceItems: NavItem[] = [
    { title: 'Projects', href: '/projects', icon: Folder },
    { title: 'Notes', href: '/notes', icon: FileText },
    { title: 'Calendar', href: '/calendar', icon: Calendar },
];

const toolItems: NavItem[] = [
    { title: 'Vault', href: '/secrets', icon: KeyRound },
    { title: 'MCP', href: '/mcp-access', icon: Plug },
    { title: 'Settings', href: '/settings', icon: Settings },
];
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child tooltip="Dashboard">
                        <Link :href="route('dashboard')" prefetch aria-label="Taskflow — dashboard">
                            <AppLogoIcon class="size-8! shrink-0" />
                            <span class="text-[17px] font-bold tracking-tight">
                                <span class="text-sidebar-accent-foreground">Task</span><span class="text-sidebar-highlight">flow</span>
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-1">
            <NavMain :items="overviewItems" />
            <NavMain label="Workspace" :items="workspaceItems" />
            <NavMain label="Tools" :items="toolItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
