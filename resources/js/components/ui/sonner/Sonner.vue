<script lang="ts" setup>
import { Toaster as Sonner, type ToasterProps } from 'vue-sonner'

const props = defineProps<ToasterProps>()
</script>

<template>
  <Sonner
    class="toaster group"
    v-bind="props"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--success-bg': 'var(--popover)',
      '--success-text': 'var(--success)',
      '--success-border': 'var(--border)',
      '--error-bg': 'var(--popover)',
      '--error-text': 'var(--destructive)',
      '--error-border': 'var(--border)',
      '--warning-bg': 'var(--popover)',
      '--warning-text': 'var(--warning)',
      '--warning-border': 'var(--border)',
      '--info-bg': 'var(--popover)',
      '--info-text': 'var(--popover-foreground)',
      '--info-border': 'var(--border)',
    }"
  />
</template>
