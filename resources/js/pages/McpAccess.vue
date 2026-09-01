<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, KeyRound, LoaderCircle, Plug, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface ApiToken {
    id: string;
    name: string;
    created_at: string | null;
    expires_at: string | null;
}

interface Connection {
    client_id: string;
    name: string;
    authorized_at: string | null;
    last_activity_at: string | null;
    tokens: number;
}

const props = defineProps<{
    mcpUrl: string;
    infoUrl: string;
    apiTokens: ApiToken[];
    connections: Connection[];
    plainTextToken?: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'MCP', href: '/mcp-access' },
];

const tokenForm = useForm({ name: '' });

const createToken = () => {
    tokenForm.post(route('mcp.tokens.store'), {
        preserveScroll: true,
        onSuccess: () => tokenForm.reset(),
    });
};

const revokeToken = (token: ApiToken) => {
    router.delete(route('mcp.tokens.destroy', token.id), {
        preserveScroll: true,
        onSuccess: () => toast.success(`Token "${token.name}" revoked`),
    });
};

const revokeConnection = (connection: Connection) => {
    router.delete(route('mcp.connections.destroy', connection.client_id), {
        preserveScroll: true,
        onSuccess: () => toast.success(`${connection.name} disconnected`),
    });
};

const copied = ref<string | null>(null);

const copy = async (value: string, label: string) => {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = label;
        toast.success('Copied to clipboard');
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        toast.error('Could not copy — select and copy manually');
    }
};

const claudeCommand = `claude mcp add --transport http taskflow ${props.mcpUrl}`;

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="MCP" />

        <PageContainer>
            <PageHeader
                title="MCP access"
                description="Connect AI agents to your workspace — same powers as the dashboard, scoped to your account."
                :icon="Plug"
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <a :href="infoUrl" target="_blank">
                            Setup guide
                            <ExternalLink class="size-4" />
                        </a>
                    </Button>
                </template>
            </PageHeader>

            <div class="space-y-4">
                <!-- Connect card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-semibold">Connect a client</CardTitle>
                        <CardDescription>
                            OAuth is the easy path — run the command and sign in when the browser opens. Headless clients can send an API token as
                            <code class="bg-muted rounded px-1 py-0.5 font-mono text-xs">Authorization: Bearer &lt;token&gt;</code> instead.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="space-y-2">
                                <p class="text-muted-foreground text-xs font-medium">Server endpoint</p>
                                <div class="flex items-center gap-2">
                                    <code class="bg-muted min-w-0 flex-1 overflow-x-auto rounded-md px-3 py-2 font-mono text-xs whitespace-nowrap">{{
                                        mcpUrl
                                    }}</code>
                                    <Button variant="outline" size="sm" @click="copy(mcpUrl, 'url')">
                                        <Check v-if="copied === 'url'" class="text-success size-4" />
                                        <Copy v-else class="size-4" />
                                    </Button>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <p class="text-muted-foreground text-xs font-medium">Add to Claude Code</p>
                                <div class="flex items-center gap-2">
                                    <code class="bg-muted min-w-0 flex-1 overflow-x-auto rounded-md px-3 py-2 font-mono text-xs whitespace-nowrap">{{
                                        claudeCommand
                                    }}</code>
                                    <Button variant="outline" size="sm" @click="copy(claudeCommand, 'cmd')">
                                        <Check v-if="copied === 'cmd'" class="text-success size-4" />
                                        <Copy v-else class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Credentials: API tokens / OAuth connections -->
                <Card>
                    <CardContent>
                        <Tabs default-value="tokens">
                            <TabsList class="bg-muted mb-4 h-9 rounded-lg p-1">
                                <TabsTrigger value="tokens">API tokens</TabsTrigger>
                                <TabsTrigger value="connections">Connected apps</TabsTrigger>
                            </TabsList>

                            <TabsContent value="tokens" class="space-y-4">
                                <p class="text-muted-foreground text-sm">Long-lived tokens for clients that authenticate with a Bearer header.</p>

                                <div v-if="plainTextToken" class="border-warning/40 bg-warning/10 space-y-2 rounded-lg border p-4">
                                    <p class="text-sm font-medium">Copy your new token now — it won't be shown again</p>
                                    <div class="flex items-center gap-2">
                                        <code class="bg-card min-w-0 flex-1 truncate rounded-md border px-3 py-2 font-mono text-xs">{{
                                            plainTextToken
                                        }}</code>
                                        <Button variant="outline" size="sm" @click="copy(plainTextToken!, 'token')">
                                            <Check v-if="copied === 'token'" class="text-success size-4" />
                                            <Copy v-else class="size-4" />
                                        </Button>
                                    </div>
                                </div>

                                <form @submit.prevent="createToken" class="flex items-end gap-2">
                                    <div class="grid flex-1 gap-2">
                                        <Label for="token_name">Token name</Label>
                                        <Input id="token_name" v-model="tokenForm.name" placeholder="e.g. Claude on my laptop" />
                                        <InputError :message="tokenForm.errors.name" />
                                    </div>
                                    <Button type="submit" :disabled="tokenForm.processing || !tokenForm.name">
                                        <LoaderCircle v-if="tokenForm.processing" class="size-4 animate-spin" />
                                        <KeyRound v-else class="size-4" />
                                        Create
                                    </Button>
                                </form>

                                <div v-if="apiTokens.length" class="divide-y rounded-lg border">
                                    <div v-for="token in apiTokens" :key="token.id" class="flex items-center justify-between gap-3 px-3 py-2.5">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium">{{ token.name }}</p>
                                            <p class="text-muted-foreground text-xs">
                                                Created {{ formatDate(token.created_at) }}
                                                <template v-if="token.expires_at"> · expires {{ formatDate(token.expires_at) }}</template>
                                            </p>
                                        </div>
                                        <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="revokeToken(token)">
                                            <Trash2 class="size-4" />
                                            Revoke
                                        </Button>
                                    </div>
                                </div>
                                <EmptyState
                                    v-else
                                    :icon="KeyRound"
                                    title="No API tokens yet"
                                    description="Create one above to connect a headless client with a Bearer header."
                                />
                            </TabsContent>

                            <TabsContent value="connections" class="space-y-4">
                                <p class="text-muted-foreground text-sm">Apps authorized through OAuth — revoke one to force it to sign in again.</p>

                                <div v-if="connections.length" class="divide-y rounded-lg border">
                                    <div
                                        v-for="connection in connections"
                                        :key="connection.client_id"
                                        class="flex items-center justify-between gap-3 px-3 py-2.5"
                                    >
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-md">
                                                <Plug class="size-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium">{{ connection.name }}</p>
                                                <p class="text-muted-foreground text-xs">
                                                    Authorized {{ formatDate(connection.authorized_at) }} · {{ connection.tokens }}
                                                    {{ connection.tokens === 1 ? 'session' : 'sessions' }}
                                                </p>
                                            </div>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click="revokeConnection(connection)"
                                        >
                                            <Trash2 class="size-4" />
                                            Revoke
                                        </Button>
                                    </div>
                                </div>
                                <EmptyState
                                    v-else
                                    :icon="Plug"
                                    title="Nothing connected yet"
                                    description="Run the Claude Code command above and sign in when the browser opens — the connection appears here."
                                />
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </div>
        </PageContainer>
    </AppLayout>
</template>
