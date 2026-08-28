<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="border-border mb-6 flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div
                v-if="icon"
                class="border-border bg-card text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg border shadow-xs"
            >
                <component :is="icon" class="size-4" />
            </div>
            <div>
                <h1 class="text-foreground text-xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p v-if="description" class="text-muted-foreground mt-0.5 text-sm">{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
