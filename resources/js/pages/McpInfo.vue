<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, KeyRound, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    mcpUrl: string;
    discoveryUrl: string;
    resourceMetadataUrl: string;
}>();

const claudeCommand = `claude mcp add --transport http taskflow ${props.mcpUrl}`;

const copied = ref(false);

const copyCommand = async () => {
    try {
        await navigator.clipboard.writeText(claudeCommand);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable (insecure context) — the command stays selectable
    }
};

const toolGroups = [
    { area: 'Projects', tools: 'list, create, update, delete' },
    { area: 'Tasks', tools: 'list with filters, get, create, update, bulk status, delete' },
    { area: 'Tags', tools: 'list, create, update, delete' },
    { area: 'Notes', tools: 'list, read, create, update, delete' },
    { area: 'Calendar', tools: 'list by range, create, update, delete' },
    { area: 'Vault', tools: 'list names, read value, create, update, delete' },
];
</script>

<template>
    <div class="bg-background min-h-svh">
        <Head title="MCP server" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-8 px-6 py-12">
            <div class="flex items-center justify-between">
                <Link href="/" aria-label="Taskflow home"><AppLogo /></Link>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/mcp-access">Manage access</Link>
                </Button>
            </div>

            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">Taskflow MCP server</h1>
                <p class="text-muted-foreground text-sm">
                    Connect an AI agent to your workspace. It gets the same powers as the dashboard — always scoped to your account, revocable
                    anytime.
                </p>
            </div>

            <!-- Connect -->
            <div class="bg-card space-y-3 rounded-lg border p-5">
                <h2 class="text-sm font-semibold">Connect from Claude Code</h2>
                <div class="flex items-center gap-2">
                    <code class="bg-muted min-w-0 flex-1 overflow-x-auto rounded-md px-3 py-2 font-mono text-xs whitespace-nowrap">{{
                        claudeCommand
                    }}</code>
                    <Button variant="outline" size="sm" @click="copyCommand">
                        <Check v-if="copied" class="text-success size-4" />
                        <Copy v-else class="size-4" />
                    </Button>
                </div>
                <ol class="text-muted-foreground space-y-1 text-xs">
                    <li class="flex items-baseline gap-2"><span class="text-foreground font-medium">1.</span> Run the command in your terminal</li>
                    <li class="flex items-baseline gap-2"><span class="text-foreground font-medium">2.</span> Sign in when the browser opens</li>
                    <li class="flex items-baseline gap-2"><span class="text-foreground font-medium">3.</span> Approve access — Claude is connected</li>
                </ol>
            </div>

            <!-- Auth -->
            <div class="grid gap-4">
                <div class="bg-card flex flex-col gap-3 rounded-lg border p-5">
                    <div class="flex items-center gap-2.5">
                        <span class="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-md">
                            <ShieldCheck class="size-4" />
                        </span>
                        <h2 class="text-sm font-semibold whitespace-nowrap">OAuth sign-in</h2>
                        <Badge variant="secondary" class="ml-auto shrink-0">Recommended</Badge>
                    </div>
                    <ul class="text-muted-foreground space-y-1.5 text-sm">
                        <li class="flex items-start gap-2"><span class="bg-primary mt-1.5 size-1.5 shrink-0 rounded-full"></span> No tokens — sign in through your browser</li>
                        <li class="flex items-start gap-2"><span class="bg-primary mt-1.5 size-1.5 shrink-0 rounded-full"></span> Each app gets its own revocable connection</li>
                    </ul>
                </div>
                <div class="bg-card flex flex-col gap-3 rounded-lg border p-5">
                    <div class="flex items-center gap-2.5">
                        <span class="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-md">
                            <KeyRound class="size-4" />
                        </span>
                        <h2 class="text-sm font-semibold">API tokens</h2>
                    </div>
                    <ul class="text-muted-foreground space-y-1.5 text-sm">
                        <li class="flex items-start gap-2"><span class="bg-muted-foreground/50 mt-1.5 size-1.5 shrink-0 rounded-full"></span> Best for scripts and headless clients</li>
                        <li class="flex items-start gap-2">
                            <span class="bg-muted-foreground/50 mt-1.5 size-1.5 shrink-0 rounded-full"></span>
                            <span>Send as <code class="bg-muted rounded px-1 py-0.5 font-mono text-xs">Authorization: Bearer &lt;token&gt;</code></span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- What agents can do -->
            <div class="bg-card space-y-3 rounded-lg border p-5">
                <h2 class="text-sm font-semibold">Available tools</h2>
                <div class="divide-y">
                    <div v-for="group in toolGroups" :key="group.area" class="flex items-baseline justify-between gap-4 py-2">
                        <span class="text-sm font-medium">{{ group.area }}</span>
                        <span class="text-muted-foreground text-right text-sm">{{ group.tools }}</span>
                    </div>
                </div>
            </div>

            <!-- Technical details -->
            <div class="bg-card space-y-3 rounded-lg border p-5">
                <h2 class="text-sm font-semibold">Technical details</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-muted-foreground">Endpoint</dt>
                        <dd>
                            <code class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">{{ mcpUrl }}</code>
                        </dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-muted-foreground">Transport</dt>
                        <dd>Streamable HTTP — JSON-RPC 2.0 over POST</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-muted-foreground">OAuth discovery</dt>
                        <dd>
                            <a :href="discoveryUrl" target="_blank" class="text-primary inline-flex items-center gap-1 hover:underline">
                                authorization server <ExternalLink class="size-3" />
                            </a>
                        </dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <dt class="text-muted-foreground">Resource metadata</dt>
                        <dd>
                            <a :href="resourceMetadataUrl" target="_blank" class="text-primary inline-flex items-center gap-1 hover:underline">
                                protected resource <ExternalLink class="size-3" />
                            </a>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>
