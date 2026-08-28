<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon?: Component;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="border-border bg-muted/30 flex flex-col items-center justify-center rounded-lg border border-dashed px-6 py-10 text-center">
        <div v-if="icon" class="bg-muted text-muted-foreground mb-3 flex size-10 items-center justify-center rounded-lg">
            <component :is="icon" class="size-5" />
        </div>
        <h3 class="text-foreground text-sm font-semibold">{{ title }}</h3>
        <p v-if="description" class="text-muted-foreground mt-1 max-w-sm text-sm">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
