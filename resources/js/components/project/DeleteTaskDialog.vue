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
import { Loader2 } from 'lucide-vue-next';

defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    taskToDelete: {
        type: Object,
        default: null,
    },
    // True while the delete request is in flight — disables both buttons
    processing: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:open', 'confirm', 'cancel']);
</script>

<template>
    <AlertDialog :open="isOpen" @update:open="$emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete task</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete "{{ taskToDelete?.title }}"? This cannot be undone and will permanently remove the task and all of
                    its subtasks.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="processing" @click="$emit('cancel')"> Cancel </AlertDialogCancel>
                <AlertDialogAction
                    :disabled="processing"
                    @click="$emit('confirm')"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                    <Loader2 v-if="processing" class="size-4 animate-spin" />
                    {{ processing ? 'Deleting…' : 'Delete task' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
