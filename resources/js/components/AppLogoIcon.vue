<script setup lang="ts">
// Taskflow mark: three kanban columns (tasks moving through a board).
// 'tile' is the brand-constant version (cobalt square, white mark) for chrome and favicons;
// 'mark' inherits currentColor for inline/accent use.
withDefaults(defineProps<{ variant?: 'tile' | 'mark' }>(), { variant: 'tile' });
</script>

<template>
    <svg v-if="variant === 'tile'" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Taskflow">
        <rect width="32" height="32" rx="8" fill="#2156CA" />
        <g fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round">
            <path d="M10 9 V19" />
            <path d="M16 9 V23" />
            <path d="M22 9 V15" />
        </g>
    </svg>
    <svg
        v-else
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="Taskflow"
        fill="none"
        stroke="currentColor"
        stroke-width="2.6"
        stroke-linecap="round"
    >
        <path d="M6 5 V15" />
        <path d="M12 5 V19" />
        <path d="M18 5 V11" />
    </svg>
</template>
