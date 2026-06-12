<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, EyeOff, LoaderCircle, RotateCcw } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

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

// ───────── OTP sign-in ─────────
const otpStep = ref<'request' | 'verify'>('request');
const otpForm = useForm({
    email: '',
    code: '',
    remember: false,
});
const resendNotice = ref('');

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
            nextTick(() => codeInput.value?.focus());
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

const codeInput = ref<HTMLInputElement | null>(null);
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

// Decorative task rows for the left panel
const tasks = [
    { label: 'Finalize Q3 roadmap', done: true, tag: 'Strategy' },
    { label: 'Review design handoff', done: true, tag: 'Design' },
    { label: 'Ship auth refactor', done: false, tag: 'Engineering' },
    { label: 'Sync with growth team', done: false, tag: 'Ops' },
];
</script>

<template>
    <Head title="Log in">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..700;1,9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="login-shell">
        <!-- ───────────── Left / Editorial panel ───────────── -->
        <aside class="brand-panel">
            <div class="brand-noise" aria-hidden="true" />
            <div class="brand-glow" aria-hidden="true" />

            <header class="brand-top">
                <Link :href="route('dashboard')" class="brand-mark">
                    <span class="brand-dot" />
                    <span>Taskflow</span>
                </Link>
                <span class="brand-meta">EST. 2026</span>
            </header>

            <div class="brand-center">
                <p class="brand-eyebrow">Where work finds its rhythm</p>
                <h1 class="brand-headline">
                    Plan less.<br />
                    <em>Finish</em> more.
                </h1>

                <ul class="task-stack">
                    <li
                        v-for="(t, i) in tasks"
                        :key="t.label"
                        class="task-row"
                        :class="{ 'is-done': t.done }"
                        :style="{ animationDelay: 220 + i * 90 + 'ms' }"
                    >
                        <span class="task-check">
                            <svg viewBox="0 0 16 16" fill="none">
                                <path d="M3.5 8.5l3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="task-label">{{ t.label }}</span>
                        <span class="task-tag">{{ t.tag }}</span>
                    </li>
                </ul>
            </div>

            <footer class="brand-foot">
                <p>Trusted by teams who ship on time.</p>
            </footer>
        </aside>

        <!-- ───────────── Right / Form panel ───────────── -->
        <main class="form-panel">
            <div class="form-inner">
                <Link :href="route('dashboard')" class="mobile-mark">
                    <span class="brand-dot" />
                    <span>Taskflow</span>
                </Link>

                <div class="form-heading">
                    <p class="form-eyebrow">Welcome back</p>
                    <h2>{{ heading }}</h2>
                    <p class="form-sub">{{ subheading }}</p>
                </div>

                <div v-if="status" class="status-banner">
                    {{ status }}
                </div>

                <!-- Method toggle -->
                <div class="mode-switch" role="tablist" aria-label="Sign-in method">
                    <button
                        type="button"
                        class="mode-tab"
                        :class="{ 'is-active': mode === 'password' }"
                        role="tab"
                        :aria-selected="mode === 'password'"
                        @click="switchMode('password')"
                    >
                        Password
                    </button>
                    <button
                        type="button"
                        class="mode-tab"
                        :class="{ 'is-active': mode === 'otp' }"
                        role="tab"
                        :aria-selected="mode === 'otp'"
                        @click="switchMode('otp')"
                    >
                        Email code
                    </button>
                    <span class="mode-thumb" :class="mode === 'otp' ? 'right' : 'left'" aria-hidden="true" />
                </div>

                <!-- ───── Password flow ───── -->
                <form v-if="mode === 'password'" @submit.prevent="submit" class="auth-form">
                    <label class="field">
                        <span class="field-label">Email address</span>
                        <input
                            id="email"
                            type="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="you@company.com"
                            :class="{ 'has-error': form.errors.email }"
                        />
                        <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                    </label>

                    <label class="field">
                        <span class="field-row">
                            <span class="field-label">Password</span>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="forgot-link"
                                :tabindex="5"
                            >
                                Forgot?
                            </Link>
                        </span>
                        <span class="input-wrap">
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                v-model="form.password"
                                placeholder="••••••••"
                                :class="{ 'has-error': form.errors.password }"
                            />
                            <button
                                type="button"
                                class="peek-btn"
                                tabindex="-1"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="size-4" />
                                <Eye v-else class="size-4" />
                            </button>
                        </span>
                        <span v-if="form.errors.password" class="field-error">{{ form.errors.password }}</span>
                    </label>

                    <label class="remember">
                        <input type="checkbox" v-model="form.remember" :tabindex="3" />
                        <span class="remember-box" aria-hidden="true">
                            <svg viewBox="0 0 16 16" fill="none">
                                <path d="M3.5 8.5l3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>Keep me signed in</span>
                    </label>

                    <button type="submit" class="submit-btn" :tabindex="4" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                        <span>{{ form.processing ? 'Signing in…' : 'Sign in' }}</span>
                        <svg v-if="!form.processing" class="arrow" viewBox="0 0 20 20" fill="none">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </form>

                <!-- ───── OTP: request code ───── -->
                <form v-else-if="otpStep === 'request'" @submit.prevent="sendOtp" class="auth-form">
                    <label class="field">
                        <span class="field-label">Email address</span>
                        <input
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            v-model="otpForm.email"
                            placeholder="you@company.com"
                            :class="{ 'has-error': otpForm.errors.email }"
                        />
                        <span v-if="otpForm.errors.email" class="field-error">{{ otpForm.errors.email }}</span>
                    </label>

                    <button type="submit" class="submit-btn" :disabled="otpForm.processing">
                        <LoaderCircle v-if="otpForm.processing" class="size-4 animate-spin" />
                        <span>{{ otpForm.processing ? 'Sending code…' : 'Send sign-in code' }}</span>
                        <svg v-if="!otpForm.processing" class="arrow" viewBox="0 0 20 20" fill="none">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </form>

                <!-- ───── OTP: verify code ───── -->
                <form v-else @submit.prevent="verifyOtp" class="auth-form">
                    <label class="field">
                        <span class="field-label">6-digit code</span>
                        <input
                            ref="codeInput"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            required
                            v-model="otpForm.code"
                            @input="onlyDigits"
                            placeholder="••••••"
                            class="otp-input"
                            :class="{ 'has-error': otpForm.errors.code }"
                        />
                        <span v-if="otpForm.errors.code" class="field-error">{{ otpForm.errors.code }}</span>
                        <span v-else-if="resendNotice" class="field-note">{{ resendNotice }}</span>
                    </label>

                    <label class="remember">
                        <input type="checkbox" v-model="otpForm.remember" />
                        <span class="remember-box" aria-hidden="true">
                            <svg viewBox="0 0 16 16" fill="none">
                                <path d="M3.5 8.5l3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>Keep me signed in</span>
                    </label>

                    <button type="submit" class="submit-btn" :disabled="otpForm.processing || otpForm.code.length < 6">
                        <LoaderCircle v-if="otpForm.processing" class="size-4 animate-spin" />
                        <span>{{ otpForm.processing ? 'Verifying…' : 'Verify & sign in' }}</span>
                        <svg v-if="!otpForm.processing" class="arrow" viewBox="0 0 20 20" fill="none">
                            <path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div class="otp-actions">
                        <button type="button" class="ghost-btn" @click="backToRequest">
                            <ArrowLeft class="size-3.5" />
                            Change email
                        </button>
                        <button type="button" class="ghost-btn" :disabled="otpForm.processing" @click="resendOtp">
                            <RotateCcw class="size-3.5" />
                            Resend code
                        </button>
                    </div>
                </form>

                <p class="form-legal">
                    Protected workspace · Your session is encrypted end-to-end.
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.login-shell {
    --ink: #14110f;
    --ink-soft: #1d1916;
    --cream: #f6f1e9;
    --paper: #fbf8f3;
    --saffron: #e0852f;
    --saffron-bright: #f59e3f;
    --line: #e6ddd0;
    --muted: #8a7f72;

    display: grid;
    grid-template-columns: 1.05fr 1fr;
    min-height: 100svh;
    font-family: 'Hanken Grotesk', ui-sans-serif, system-ui, sans-serif;
    color: var(--ink);
    background: var(--paper);
    -webkit-font-smoothing: antialiased;
}

/* ───────── Left panel ───────── */
.brand-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 3rem 3.25rem;
    overflow: hidden;
    color: var(--cream);
    background:
        radial-gradient(120% 90% at 12% 8%, #2a2420 0%, transparent 55%),
        linear-gradient(155deg, #1a1613 0%, #100d0b 70%);
    isolation: isolate;
}

.brand-noise {
    position: absolute;
    inset: 0;
    z-index: -1;
    opacity: 0.5;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.35'/%3E%3C/svg%3E");
    mix-blend-mode: overlay;
}

.brand-glow {
    position: absolute;
    width: 520px;
    height: 520px;
    right: -160px;
    bottom: -180px;
    z-index: -1;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(224, 133, 47, 0.32) 0%, transparent 65%);
    filter: blur(8px);
    animation: drift 14s ease-in-out infinite alternate;
}

@keyframes drift {
    to {
        transform: translate(-50px, -40px) scale(1.12);
    }
}

.brand-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand-mark {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 600;
    font-size: 1.15rem;
    letter-spacing: -0.01em;
    color: var(--cream);
    text-decoration: none;
}

.brand-dot {
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 50%;
    background: var(--saffron-bright);
    box-shadow: 0 0 0 4px rgba(245, 158, 63, 0.18);
}

.brand-meta {
    font-size: 0.72rem;
    letter-spacing: 0.22em;
    color: rgba(246, 241, 233, 0.45);
}

.brand-center {
    max-width: 30rem;
}

.brand-eyebrow {
    font-size: 0.78rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--saffron-bright);
    margin-bottom: 1.1rem;
}

.brand-headline {
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 500;
    font-size: clamp(2.6rem, 4vw, 3.6rem);
    line-height: 1.02;
    letter-spacing: -0.02em;
    margin: 0 0 2.4rem;
}

.brand-headline em {
    font-style: italic;
    color: var(--saffron-bright);
}

.task-stack {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.task-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.8rem 1rem;
    border-radius: 0.85rem;
    background: rgba(246, 241, 233, 0.04);
    border: 1px solid rgba(246, 241, 233, 0.08);
    backdrop-filter: blur(2px);
    opacity: 0;
    transform: translateY(14px);
    animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes rise {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.task-check {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.5rem;
    border: 1.5px solid rgba(246, 241, 233, 0.25);
    color: transparent;
    transition: all 0.3s ease;
}

.task-check svg {
    width: 0.85rem;
    height: 0.85rem;
}

.task-row.is-done .task-check {
    background: var(--saffron);
    border-color: var(--saffron);
    color: var(--ink);
}

.task-label {
    flex: 1;
    font-size: 0.92rem;
    font-weight: 500;
    color: rgba(246, 241, 233, 0.92);
}

.task-row.is-done .task-label {
    color: rgba(246, 241, 233, 0.5);
    text-decoration: line-through;
    text-decoration-color: rgba(224, 133, 47, 0.6);
}

.task-tag {
    font-size: 0.68rem;
    letter-spacing: 0.04em;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    color: rgba(246, 241, 233, 0.6);
    background: rgba(246, 241, 233, 0.06);
}

.brand-foot {
    font-size: 0.85rem;
    color: rgba(246, 241, 233, 0.4);
}

/* ───────── Right panel ───────── */
.form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1.5rem;
    background:
        radial-gradient(100% 60% at 100% 0%, #fdfbf7 0%, transparent 60%),
        var(--paper);
}

.form-inner {
    width: 100%;
    max-width: 25rem;
    animation: fade-up 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    animation-delay: 0.1s;
}

@keyframes fade-up {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
}

.mobile-mark {
    display: none;
    align-items: center;
    gap: 0.55rem;
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--ink);
    text-decoration: none;
    margin-bottom: 2rem;
}

.form-heading h2 {
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 500;
    font-size: 1.95rem;
    line-height: 1.1;
    letter-spacing: -0.02em;
    margin: 0.35rem 0 0.5rem;
}

.form-eyebrow {
    font-size: 0.76rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--saffron);
    font-weight: 600;
}

.form-sub {
    color: var(--muted);
    font-size: 0.95rem;
}

.status-banner {
    margin-top: 1.4rem;
    padding: 0.75rem 1rem;
    border-radius: 0.7rem;
    font-size: 0.88rem;
    font-weight: 500;
    color: #2f6b3d;
    background: #e8f3ea;
    border: 1px solid #cfe6d4;
}

.auth-form {
    margin-top: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.field-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #4a4138;
}

.forgot-link {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--saffron);
    text-decoration: none;
    transition: opacity 0.2s;
}

.forgot-link:hover {
    opacity: 0.7;
}

.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.auth-form input[type='email'],
.auth-form input[type='password'],
.auth-form input[type='text'] {
    width: 100%;
    padding: 0.8rem 1rem;
    font-size: 0.95rem;
    font-family: inherit;
    color: var(--ink);
    background: #ffffff;
    border: 1.5px solid var(--line);
    border-radius: 0.7rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

.input-wrap input {
    padding-right: 2.9rem !important;
}

.auth-form input::placeholder {
    color: #b6ab9c;
}

.auth-form input:focus {
    border-color: var(--saffron);
    box-shadow: 0 0 0 4px rgba(224, 133, 47, 0.13);
    background: #fffdfa;
}

.auth-form input.has-error {
    border-color: #d6584f;
    box-shadow: 0 0 0 4px rgba(214, 88, 79, 0.1);
}

.peek-btn {
    position: absolute;
    right: 0.65rem;
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border: none;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    border-radius: 0.45rem;
    transition: color 0.2s, background 0.2s;
}

.peek-btn:hover {
    color: var(--ink);
    background: var(--cream);
}

.field-error {
    font-size: 0.8rem;
    color: #c4493f;
    font-weight: 500;
}

.remember {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    font-size: 0.88rem;
    color: #4a4138;
    user-select: none;
}

.remember input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.remember-box {
    display: grid;
    place-items: center;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 0.4rem;
    border: 1.5px solid var(--line);
    background: #fff;
    color: transparent;
    transition: all 0.2s ease;
}

.remember-box svg {
    width: 0.8rem;
    height: 0.8rem;
}

.remember input:checked + .remember-box {
    background: var(--saffron);
    border-color: var(--saffron);
    color: #fff;
}

.remember input:focus-visible + .remember-box {
    box-shadow: 0 0 0 4px rgba(224, 133, 47, 0.18);
}

.submit-btn {
    margin-top: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.9rem 1.25rem;
    font-family: inherit;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--cream);
    background: var(--ink);
    border: none;
    border-radius: 0.7rem;
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.25s ease, background 0.2s;
    box-shadow: 0 10px 24px -12px rgba(20, 17, 15, 0.6);
}

.submit-btn:hover:not(:disabled) {
    background: var(--ink-soft);
    transform: translateY(-1px);
    box-shadow: 0 16px 30px -12px rgba(20, 17, 15, 0.7);
}

.submit-btn:active:not(:disabled) {
    transform: translateY(0);
}

.submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.submit-btn .arrow {
    width: 1.1rem;
    height: 1.1rem;
    transition: transform 0.22s ease;
}

.submit-btn:hover:not(:disabled) .arrow {
    transform: translateX(4px);
}

.form-legal {
    margin-top: 2rem;
    font-size: 0.78rem;
    text-align: center;
    color: #a99e8f;
}

/* ───────── Mode switch ───────── */
.mode-switch {
    position: relative;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.25rem;
    margin-top: 1.75rem;
    padding: 0.3rem;
    background: #f1ebe1;
    border: 1px solid var(--line);
    border-radius: 0.8rem;
}

.mode-tab {
    position: relative;
    z-index: 1;
    padding: 0.55rem 0.5rem;
    font-family: inherit;
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--muted);
    background: transparent;
    border: none;
    border-radius: 0.55rem;
    cursor: pointer;
    transition: color 0.25s ease;
}

.mode-tab.is-active {
    color: var(--ink);
}

.mode-thumb {
    position: absolute;
    z-index: 0;
    top: 0.3rem;
    bottom: 0.3rem;
    width: calc(50% - 0.42rem);
    background: #ffffff;
    border-radius: 0.55rem;
    box-shadow: 0 2px 8px -3px rgba(20, 17, 15, 0.25);
    transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.mode-thumb.left {
    transform: translateX(0.12rem);
}

.mode-thumb.right {
    transform: translateX(calc(100% + 0.6rem));
}

/* ───────── OTP code input ───────── */
.otp-input {
    text-align: center;
    font-size: 1.5rem !important;
    font-weight: 600;
    letter-spacing: 0.5em;
    padding-left: 0.5em !important;
    font-variant-numeric: tabular-nums;
}

.otp-input::placeholder {
    letter-spacing: 0.4em;
}

.field-note {
    font-size: 0.8rem;
    color: #2f6b3d;
    font-weight: 500;
}

.otp-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.25rem;
}

.ghost-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.2rem;
    font-family: inherit;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: color 0.2s;
}

.ghost-btn:hover:not(:disabled) {
    color: var(--saffron);
}

.ghost-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ───────── Responsive ───────── */
@media (max-width: 900px) {
    .login-shell {
        grid-template-columns: 1fr;
    }

    .brand-panel {
        display: none;
    }

    .mobile-mark {
        display: inline-flex;
    }
}

@media (prefers-reduced-motion: reduce) {
    .task-row,
    .form-inner,
    .brand-glow {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}
</style>
