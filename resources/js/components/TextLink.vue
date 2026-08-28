<script setup lang="ts">
import { Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

interface Props {
    href: string;
    tabindex?: number;
    method?: Method;
    as?: string;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-foreground decoration-muted-foreground/40 focus-visible:ring-ring/50 rounded-sm underline underline-offset-4 transition-colors duration-150 hover:decoration-current! focus-visible:ring-2 focus-visible:outline-none"
    >
        <slot />
    </Link>
</template>
