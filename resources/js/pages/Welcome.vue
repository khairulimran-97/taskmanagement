<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, FolderKanban, KeyRound, ListChecks, NotebookPen, Tags } from 'lucide-vue-next';

// Presentational only — mirrors the feature areas that exist in the app shell.
const features = [
    {
        icon: FolderKanban,
        title: 'Projects',
        description: 'Group related tasks into projects and see progress at a glance.',
    },
    {
        icon: ListChecks,
        title: 'Tasks',
        description: 'Statuses, priorities, and due dates keep every piece of work moving.',
    },
    {
        icon: CalendarDays,
        title: 'Calendar',
        description: 'Deadlines and events on one timeline, dragged into place when plans change.',
    },
    {
        icon: NotebookPen,
        title: 'Notes',
        description: 'Rich-text notes with images, kept right next to the work they describe.',
    },
    {
        icon: Tags,
        title: 'Tags',
        description: 'Label tasks your way and filter down to what matters right now.',
    },
    {
        icon: KeyRound,
        title: 'Secret vault',
        description: 'Encrypted storage for the credentials and keys you reach for daily.',
    },
];

// Static hero preview — demonstrates the app's status language without real data.
const previewTasks = [
    { label: 'Publish release notes', status: 'Completed', dotClass: 'bg-success', done: true },
    { label: 'Review onboarding flow', status: 'In progress', dotClass: 'bg-primary', done: false },
    { label: 'Renew SSL certificate', status: 'Due soon', dotClass: 'bg-warning', done: false },
];
</script>

<template>
    <Head title="Welcome" />
    <div class="bg-background text-foreground flex min-h-screen flex-col">
        <header class="mx-auto w-full max-w-5xl px-6 py-5">
            <nav class="flex items-center justify-between" aria-label="Main">
                <AppLogo />
                <div class="flex items-center gap-2">
                    <Button v-if="$page.props.auth.user" as-child variant="outline">
                        <Link :href="route('dashboard')">Dashboard</Link>
                    </Button>
                    <Button v-else as-child variant="outline">
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                </div>
            </nav>
        </header>

        <main class="flex-1">
            <!-- Hero -->
            <section class="mx-auto w-full max-w-5xl px-6 pt-16 pb-20 sm:pt-24">
                <div class="mx-auto max-w-2xl text-center">
                    <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">Stay on top of every project</h1>
                    <p class="text-muted-foreground mt-4 text-base sm:text-lg">
                        Taskflow keeps your tasks, deadlines, notes, and credentials in one quiet workspace — so the next step is always obvious.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Button v-if="$page.props.auth.user" as-child size="lg">
                            <Link :href="route('dashboard')">Open dashboard</Link>
                        </Button>
                        <Button v-else as-child size="lg">
                            <Link :href="route('login')">Log in</Link>
                        </Button>
                    </div>
                </div>

                <!-- Static product preview (decorative) -->
                <div aria-hidden="true" class="mx-auto mt-14 max-w-md">
                    <div class="border-border bg-card rounded-lg border shadow-xs">
                        <div class="border-border flex items-center justify-between border-b px-4 py-2.5">
                            <span class="text-muted-foreground text-xs font-medium">Today</span>
                            <span class="text-muted-foreground text-xs tabular-nums">3 tasks</span>
                        </div>
                        <ul class="divide-border divide-y">
                            <li v-for="task in previewTasks" :key="task.label" class="flex items-center gap-3 px-4 py-2.5">
                                <span class="size-1.5 shrink-0 rounded-full" :class="task.dotClass" />
                                <span class="flex-1 truncate text-sm" :class="task.done ? 'text-muted-foreground line-through' : ''">
                                    {{ task.label }}
                                </span>
                                <span class="text-muted-foreground text-xs">{{ task.status }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section class="mx-auto w-full max-w-5xl px-6 pb-24">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-2xl font-semibold tracking-tight">Everything in one place</h2>
                    <p class="text-muted-foreground mt-2 text-sm">The essentials for planning and finishing work, with nothing extra.</p>
                </div>
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="feature in features" :key="feature.title" class="border-border bg-card rounded-lg border p-5 shadow-xs">
                        <div class="bg-muted text-muted-foreground flex size-8 items-center justify-center rounded-md">
                            <component :is="feature.icon" class="size-4" />
                        </div>
                        <h3 class="mt-3 text-sm font-semibold">{{ feature.title }}</h3>
                        <p class="text-muted-foreground mt-1 text-sm">{{ feature.description }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-border border-t">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-center px-6 py-6">
                <AppLogo />
            </div>
        </footer>
    </div>
</template>
