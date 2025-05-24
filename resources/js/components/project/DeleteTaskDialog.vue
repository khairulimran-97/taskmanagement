<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

defineProps({
    isOpen: {
        type: Boolean,
        required: true
    },
    taskToDelete: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['update:open', 'confirm', 'cancel']);
</script>

<template>
    <AlertDialog :open="isOpen" @update:open="$emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Task</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete "{{ taskToDelete?.title }}"? This action cannot be undone and will permanently remove the task
                    and all its subtasks.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="$emit('cancel')">
                    Cancel
                </AlertDialogCancel>
                <AlertDialogAction @click="$emit('confirm')" class="bg-red-600 hover:bg-red-700 focus:ring-red-600">
                    Delete Task
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
