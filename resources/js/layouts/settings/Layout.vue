<script setup lang="ts">
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
    },
    {
        title: 'Password',
        href: '/settings/password',
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
    },
];

const page = usePage();

// Normalize the trailing slash so "/settings/profile/" still matches its nav item
const location = (page.props.ziggy as { location?: string } | undefined)?.location;
const currentPath = location ? new URL(location).pathname.replace(/\/+$/, '') : '';

const isActive = (href: string) => currentPath === href || currentPath.startsWith(`${href}/`);
</script>

<template>
    <PageContainer>
        <PageHeader title="Settings" description="Manage your profile and account settings" />

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1" aria-label="Settings sections">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', isActive(item.href) ? 'bg-muted text-foreground' : 'text-muted-foreground']"
                        as-child
                    >
                        <Link :href="item.href" :aria-current="isActive(item.href) ? 'page' : undefined">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="lg:hidden" />

            <div class="flex-1 lg:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </PageContainer>
</template>
