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
    <div
        class="group relative overflow-hidden rounded-xl border border-border bg-card p-5 transition-all hover:border-primary/40 hover:shadow-sm"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
                <p class="font-display text-3xl font-semibold leading-none tracking-tight text-foreground">
                    {{ value }}
                </p>
                <p v-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
            </div>
            <div
                v-if="icon"
                :class="[
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors',
                    accent ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground group-hover:text-primary',
                ]"
            >
                <component :is="icon" class="h-5 w-5" />
            </div>
        </div>
    </div>
</template>
