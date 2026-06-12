<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div class="mb-7 flex flex-col gap-3 border-b border-border/70 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5">
            <div
                v-if="icon"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/12 text-primary"
            >
                <component :is="icon" class="h-5 w-5" />
            </div>
            <div class="space-y-0.5">
                <h1 class="font-display text-[1.7rem] font-semibold leading-tight tracking-tight text-foreground">
                    {{ title }}
                </h1>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
