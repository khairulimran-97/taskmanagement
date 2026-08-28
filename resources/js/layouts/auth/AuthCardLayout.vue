<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-sm flex-col gap-6">
            <!-- "/" resolves for guests (login) and authed users (dashboard) alike -->
            <Link
                href="/"
                aria-label="Taskflow home"
                class="focus-visible:ring-ring/50 self-center rounded-md p-1 transition-opacity duration-150 hover:opacity-80 focus-visible:ring-2 focus-visible:outline-none"
            >
                <AppLogo />
            </Link>

            <Card>
                <CardHeader v-if="title || description" class="text-center">
                    <CardTitle v-if="title" class="text-xl tracking-tight">{{ title }}</CardTitle>
                    <CardDescription v-if="description">
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
