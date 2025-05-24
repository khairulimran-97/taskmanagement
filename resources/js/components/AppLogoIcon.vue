<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 32" :class="className" v-bind="$attrs">
        <path d="M2 0h4v32H2V0Z" fill="#fff" />
        <path d="M6 16l12-16h5L12 16l11 16h-5L6 16Z" fill="#fff" />
    </svg>
</template>
