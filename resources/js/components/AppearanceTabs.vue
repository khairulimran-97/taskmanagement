<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Monitor, Moon, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="bg-muted inline-flex gap-1 rounded-lg p-1" role="group" aria-label="Theme">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            @click="updateAppearance(value)"
            :class="[
                'focus-visible:ring-ring/50 flex cursor-pointer items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none',
                appearance === value ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
            ]"
        >
            <component :is="Icon" class="size-4" />
            {{ label }}
        </button>
    </div>
</template>
