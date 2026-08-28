<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const quote = page.props.quote as { message: string; author: string } | undefined;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-background relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="border-border bg-muted relative hidden h-full flex-col justify-between border-r p-10 lg:flex">
            <!-- "/" resolves for guests (login) and authed users (dashboard) alike -->
            <Link
                href="/"
                aria-label="Taskflow home"
                class="focus-visible:ring-ring/50 self-start rounded-md p-1 transition-opacity duration-150 hover:opacity-80 focus-visible:ring-2 focus-visible:outline-none"
            >
                <AppLogo />
            </Link>
            <blockquote v-if="quote" class="space-y-2">
                <p class="text-foreground text-lg font-medium">&ldquo;{{ quote.message }}&rdquo;</p>
                <footer class="text-muted-foreground text-sm">{{ quote.author }}</footer>
            </blockquote>
        </div>
        <div class="lg:p-8">
            <div class="mx-auto flex w-full max-w-sm flex-col justify-center space-y-6">
                <div class="flex flex-col space-y-2 text-center">
                    <h1 v-if="title" class="text-foreground text-xl font-semibold tracking-tight">{{ title }}</h1>
                    <p v-if="description" class="text-muted-foreground text-sm">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
