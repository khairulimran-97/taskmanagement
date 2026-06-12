<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon?: Component;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/30 px-6 py-12 text-center"
    >
        <div
            v-if="icon"
            class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <component :is="icon" class="h-6 w-6" />
        </div>
        <h3 class="font-display text-lg font-semibold text-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
