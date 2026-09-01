<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10">
        <!-- Ambient scene: the logo's three kanban bars, blown up and drifting behind the card -->
        <div aria-hidden="true" class="auth-scene pointer-events-none absolute inset-0">
            <div class="auth-wash absolute inset-0"></div>
            <div class="auth-bar auth-bar-1"></div>
            <div class="auth-bar auth-bar-2"></div>
            <div class="auth-bar auth-bar-3"></div>
        </div>

        <div class="relative flex w-full max-w-sm flex-col gap-6">
            <!-- "/" resolves for guests (login) and authed users (dashboard) alike -->
            <Link
                href="/"
                aria-label="Taskflow home"
                class="focus-visible:ring-ring/50 self-center rounded-md p-1 transition-opacity duration-150 hover:opacity-80 focus-visible:ring-2 focus-visible:outline-none"
            >
                <AppLogo />
            </Link>

            <Card class="bg-card/95 backdrop-blur-sm">
                <CardHeader v-if="title || description" class="text-center">
                    <CardTitle v-if="title" class="text-xl tracking-tight">{{ title }}</CardTitle>
                    <CardDescription v-if="description">
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<style scoped>
/* Same ambient-wash vocabulary as the workspace canvas, turned up a notch:
   the login page is a standalone moment, so it can carry more atmosphere. */
.auth-wash {
    background-image:
        radial-gradient(900px 480px at 22% -10%, color-mix(in oklab, var(--primary) 14%, transparent), transparent 70%),
        radial-gradient(760px 420px at 85% 0%, color-mix(in oklab, var(--chart-2) 10%, transparent), transparent 70%),
        radial-gradient(1100px 520px at 50% 112%, color-mix(in oklab, var(--sidebar) 26%, transparent), transparent 72%);
}

/* Three staggered columns — the AppLogo kanban bars at architectural scale.
   Token-driven fills so both themes get the same quiet glow. */
.auth-bar {
    position: absolute;
    width: clamp(110px, 15vw, 190px);
    border-radius: calc(var(--radius) + 2px);
    border: 1px solid color-mix(in oklab, var(--primary) 16%, transparent);
    background: linear-gradient(
        180deg,
        color-mix(in oklab, var(--primary) 11%, transparent),
        color-mix(in oklab, var(--primary) 3%, transparent)
    );
    transform: rotate(-8deg);
    animation: auth-drift 16s ease-in-out infinite alternate;
}

.auth-bar-1 {
    top: 8%;
    left: 12%;
    height: 58vh;
    animation-duration: 18s;
}

.auth-bar-2 {
    top: 42%;
    left: 29%;
    height: 48vh;
    border-color: color-mix(in oklab, var(--chart-2) 18%, transparent);
    background: linear-gradient(
        180deg,
        color-mix(in oklab, var(--chart-2) 9%, transparent),
        color-mix(in oklab, var(--chart-2) 2%, transparent)
    );
    animation-duration: 14s;
    animation-delay: -6s;
}

.auth-bar-3 {
    top: 2%;
    right: 10%;
    height: 66vh;
    border-color: color-mix(in oklab, var(--sidebar-highlight) 14%, transparent);
    background: linear-gradient(
        180deg,
        color-mix(in oklab, var(--sidebar-highlight) 8%, transparent),
        color-mix(in oklab, var(--sidebar-highlight) 2%, transparent)
    );
    animation-duration: 20s;
    animation-delay: -11s;
}

@keyframes auth-drift {
    from {
        transform: rotate(-8deg) translateY(-14px);
    }
    to {
        transform: rotate(-8deg) translateY(14px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .auth-bar {
        animation: none;
    }
}

/* Narrow screens: the card fills the width, so keep just the two outer
   columns peeking in from the edges. */
@media (max-width: 767px) {
    .auth-bar-2 {
        display: none;
    }
    .auth-bar-1 {
        left: -12%;
    }
    .auth-bar-3 {
        right: -14%;
    }
}
</style>
