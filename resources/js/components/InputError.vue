<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message" role="alert" aria-live="polite">
        <p class="text-destructive text-sm">
            {{ message }}
        </p>
    </div>
</template>
