<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        icon?: Component;
        hint?: string;
        accent?: boolean;
    }>(),
    { accent: false },
);
</script>

<template>
    <div class="border-border bg-card rounded-lg border p-4 shadow-xs">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-muted-foreground text-xs font-medium">{{ label }}</p>
                <p class="text-foreground mt-1.5 text-2xl leading-none font-semibold tracking-tight tabular-nums">
                    {{ value }}
                </p>
                <p v-if="hint" class="text-muted-foreground mt-1.5 text-xs">{{ hint }}</p>
            </div>
            <div
                v-if="icon"
                :class="[
                    'flex size-8 shrink-0 items-center justify-center rounded-md',
                    accent ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
                ]"
            >
                <component :is="icon" class="size-4" />
            </div>
        </div>
    </div>
</template>
