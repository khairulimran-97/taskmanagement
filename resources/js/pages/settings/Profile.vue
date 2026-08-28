<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Profile updated'),
        onError: () => {
            // Surface non-validation failures (expired session, server error) that
            // would otherwise leave the user with no feedback at all
            if (!Object.keys(form.errors).length) {
                toast.error('Could not save changes. Please try again.');
            }
        },
    });
};

// Separate form so the resend action gets its own processing/disabled state
const verificationForm = useForm({});

const resendVerification = () => {
    verificationForm.post(route('verification.send'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Profile information" description="Update your name and email address" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" class="block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError :message="form.errors.email" />

                        <p v-if="mustVerifyEmail && !user.email_verified_at" class="text-muted-foreground text-sm">
                            Your email address is unverified.
                            <button
                                type="button"
                                class="text-foreground decoration-muted-foreground/40 focus-visible:ring-ring/50 cursor-pointer rounded-sm underline underline-offset-4 transition-colors duration-150 hover:decoration-current focus-visible:ring-2 focus-visible:outline-none disabled:cursor-default disabled:opacity-50"
                                :disabled="verificationForm.processing"
                                @click="resendVerification"
                            >
                                {{ verificationForm.processing ? 'Sending…' : 'Resend verification email' }}
                            </button>
                        </p>

                        <p v-if="status === 'verification-link-sent'" role="status" class="text-success text-sm font-medium">
                            A new verification link has been sent to your email address.
                        </p>
                    </div>

                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
