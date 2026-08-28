<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <!-- "/" resolves for guests (login) and authed users (dashboard) alike -->
                    <Link
                        href="/"
                        aria-label="Taskflow home"
                        class="focus-visible:ring-ring/50 rounded-md p-1 transition-opacity duration-150 hover:opacity-80 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <AppLogo />
                    </Link>
                    <div class="space-y-1 text-center">
                        <h1 v-if="title" class="text-foreground text-xl font-semibold tracking-tight">{{ title }}</h1>
                        <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
