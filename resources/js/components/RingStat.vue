<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number; // 0-100
        label: string;
        sublabel?: string;
        size?: number;
    }>(),
    { size: 132 },
);

const radius = computed(() => props.size / 2 - 10);
const circumference = computed(() => 2 * Math.PI * radius.value);
const dash = computed(() => (Math.min(100, Math.max(0, props.value)) / 100) * circumference.value);
</script>

<template>
    <div class="flex flex-col items-center justify-center">
        <div class="relative" :style="{ width: size + 'px', height: size + 'px' }">
            <svg :width="size" :height="size" class="-rotate-90">
                <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" stroke="currentColor" class="text-muted" stroke-width="9" />
                <circle
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="radius"
                    fill="none"
                    stroke="currentColor"
                    class="text-primary transition-[stroke-dasharray] duration-700 ease-out motion-reduce:transition-none"
                    stroke-width="9"
                    stroke-linecap="round"
                    :stroke-dasharray="`${dash} ${circumference}`"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span :class="['text-foreground leading-none font-semibold tracking-tight tabular-nums', size < 110 ? 'text-lg' : 'text-2xl']"
                    >{{ Math.round(value) }}%</span
                >
            </div>
        </div>
        <p class="text-foreground mt-3 text-sm font-medium">{{ label }}</p>
        <p v-if="sublabel" class="text-muted-foreground text-xs">{{ sublabel }}</p>
    </div>
</template>
