<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Loader2, LogOut, Settings } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    user: User;
}

defineProps<Props>();

const confirmingLogout = ref(false);
const loggingOut = ref(false);

const logout = () => {
    if (loggingOut.value) return;
    loggingOut.value = true;
    router.post(
        route('logout'),
        {},
        {
            onFinish: () => {
                loggingOut.value = false;
                confirmingLogout.value = false;
                router.flushAll();
            },
        },
    );
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="route('profile.edit')" prefetch as="button">
                <Settings class="mr-2 size-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <!-- @select.prevent keeps the dropdown mounted so the nested dialog survives -->
    <DropdownMenuItem variant="destructive" class="cursor-pointer" @select.prevent="confirmingLogout = true">
        <LogOut class="mr-2 size-4" />
        Log out
    </DropdownMenuItem>

    <AlertDialog v-model:open="confirmingLogout">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Log out of Taskflow?</AlertDialogTitle>
                <AlertDialogDescription>You'll need to sign in again to get back to your workspace.</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="loggingOut">Stay signed in</AlertDialogCancel>
                <Button variant="destructive" :disabled="loggingOut" @click="logout">
                    <Loader2 v-if="loggingOut" class="size-4 animate-spin" />
                    <LogOut v-else class="size-4" />
                    {{ loggingOut ? 'Logging out…' : 'Log out' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
