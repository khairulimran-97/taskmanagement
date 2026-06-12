<script setup lang="ts">
withDefaults(defineProps<{ fluid?: boolean }>(), { fluid: false });
</script>

<template>
    <div :class="['mx-auto w-full px-4 py-6 sm:px-6', fluid ? 'max-w-none' : 'max-w-7xl']">
        <slot />
    </div>
</template>
