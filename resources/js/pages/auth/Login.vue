<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, EyeOff, LoaderCircle, RotateCcw } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

type Mode = 'password' | 'otp';
const mode = ref<Mode>('password');

// password sign-in
const form = useForm({
    email: '',
    password: '',
    remember: false,
});
const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

// OTP sign-in
const otpStep = ref<'request' | 'verify'>('request');
const otpForm = useForm({
    email: '',
    code: '',
    remember: false,
});
const resendNotice = ref('');

// Cooldown keeps the send-code endpoint from being spammed by rapid clicks
const resendCooldown = ref(0);
let cooldownTimer: ReturnType<typeof setInterval> | null = null;

const startResendCooldown = (seconds = 30) => {
    resendCooldown.value = seconds;
    if (cooldownTimer) clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
        resendCooldown.value -= 1;
        if (resendCooldown.value <= 0 && cooldownTimer) {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
    }, 1000);
};

onUnmounted(() => {
    if (cooldownTimer) clearInterval(cooldownTimer);
});

const switchMode = (next: Mode) => {
    mode.value = next;
    form.clearErrors();
    otpForm.clearErrors();
    if (next === 'otp') {
        otpForm.email = form.email;
        otpStep.value = 'request';
    } else {
        form.email = otpForm.email;
    }
};

const sendOtp = () => {
    resendNotice.value = '';
    otpForm.post(route('login.otp.send'), {
        preserveScroll: true,
        onSuccess: () => {
            otpStep.value = 'verify';
            startResendCooldown();
            nextTick(() => (codeInput.value?.$el as HTMLInputElement | undefined)?.focus());
        },
    });
};

const verifyOtp = () => {
    otpForm.post(route('login.otp.verify'), {
        preserveScroll: true,
        onError: () => otpForm.reset('code'),
    });
};

const resendOtp = () => {
    otpForm.clearErrors();
    otpForm.post(route('login.otp.send'), {
        preserveScroll: true,
        onSuccess: () => {
            resendNotice.value = 'A new code has been sent.';
            startResendCooldown();
            otpForm.reset('code');
        },
    });
};

const backToRequest = () => {
    otpStep.value = 'request';
    otpForm.reset('code');
    otpForm.clearErrors();
    resendNotice.value = '';
};

const codeInput = ref<InstanceType<typeof Input> | null>(null);
const onlyDigits = () => {
    otpForm.code = otpForm.code.replace(/\D/g, '').slice(0, 6);
};

const heading = computed(() => {
    if (mode.value === 'password') return 'Sign in to your workspace';
    return otpStep.value === 'request' ? 'Sign in with a code' : 'Enter your code';
});
const subheading = computed(() => {
    if (mode.value === 'password') return 'Pick up right where you left off.';
    return otpStep.value === 'request'
        ? "We'll email you a 6-digit sign-in code."
        : `Sent to ${otpForm.email || 'your email'}. Expires in 10 minutes.`;
});
</script>

<template>
    <AuthBase :title="heading" :description="subheading">
        <Head title="Log in" />

        <div v-if="status" role="status" class="bg-success/10 text-success mb-4 rounded-md px-3 py-2 text-center text-sm font-medium">
            {{ status }}
        </div>

        <!-- Sign-in method toggle -->
        <div class="bg-muted mb-6 grid grid-cols-2 gap-1 rounded-lg p-1" role="group" aria-label="Sign-in method">
            <button
                type="button"
                :aria-pressed="mode === 'password'"
                :class="[
                    'focus-visible:ring-ring/50 cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none',
                    mode === 'password' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
                ]"
                @click="switchMode('password')"
            >
                Password
            </button>
            <button
                type="button"
                :aria-pressed="mode === 'otp'"
                :class="[
                    'focus-visible:ring-ring/50 cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none',
                    mode === 'otp' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
                ]"
                @click="switchMode('otp')"
            >
                Email code
            </button>
        </div>

        <!-- Password flow -->
        <form v-if="mode === 'password'" @submit.prevent="submit" class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" type="email" required autofocus autocomplete="email" v-model="form.email" placeholder="you@company.com" />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm">Forgot password?</TextLink>
                </div>
                <div class="relative">
                    <Input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        v-model="form.password"
                        placeholder="Password"
                        class="pr-10"
                    />
                    <button
                        type="button"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/50 absolute inset-y-0 right-0 flex w-9 cursor-pointer items-center justify-center rounded-r-md transition-colors duration-150 focus-visible:ring-2 focus-visible:outline-none"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="remember" v-model="form.remember" />
                <Label for="remember" class="font-normal">Keep me signed in</Label>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                {{ form.processing ? 'Signing in…' : 'Sign in' }}
            </Button>
        </form>

        <!-- OTP: request code -->
        <form v-else-if="otpStep === 'request'" @submit.prevent="sendOtp" class="grid gap-6">
            <div class="grid gap-2">
                <Label for="otp-email">Email address</Label>
                <Input id="otp-email" type="email" required autofocus autocomplete="email" v-model="otpForm.email" placeholder="you@company.com" />
                <InputError :message="otpForm.errors.email" />
            </div>

            <Button type="submit" class="w-full" :disabled="otpForm.processing">
                <LoaderCircle v-if="otpForm.processing" class="size-4 animate-spin" />
                {{ otpForm.processing ? 'Sending code…' : 'Send sign-in code' }}
            </Button>
        </form>

        <!-- OTP: verify code -->
        <form v-else @submit.prevent="verifyOtp" class="grid gap-6">
            <div class="grid gap-2">
                <Label for="otp-code">6-digit code</Label>
                <Input
                    id="otp-code"
                    ref="codeInput"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    required
                    v-model="otpForm.code"
                    @input="onlyDigits"
                    placeholder="000000"
                    class="text-center text-lg font-semibold tracking-[0.4em] tabular-nums"
                />
                <InputError :message="otpForm.errors.code" />
                <p v-if="resendNotice" role="status" class="text-success text-sm">{{ resendNotice }}</p>
                <p v-if="otpForm.code.length < 6 && !otpForm.errors.code" class="text-muted-foreground text-xs">Enter all 6 digits to continue.</p>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="otp-remember" v-model="otpForm.remember" />
                <Label for="otp-remember" class="font-normal">Keep me signed in</Label>
            </div>

            <Button type="submit" class="w-full" :disabled="otpForm.processing || otpForm.code.length < 6">
                <LoaderCircle v-if="otpForm.processing" class="size-4 animate-spin" />
                {{ otpForm.processing ? 'Verifying…' : 'Verify and sign in' }}
            </Button>

            <div class="flex items-center justify-between">
                <Button type="button" variant="ghost" size="sm" @click="backToRequest">
                    <ArrowLeft class="size-4" />
                    Change email
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="tabular-nums"
                    :disabled="otpForm.processing || resendCooldown > 0"
                    @click="resendOtp"
                >
                    <RotateCcw class="size-4" />
                    {{ resendCooldown > 0 ? `Resend in ${resendCooldown}s` : 'Resend code' }}
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
