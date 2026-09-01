<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Apply the system dark preference before first paint to avoid a flash --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';
            if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <title>Authorize application - {{ config('app.name', 'Taskflow') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased bg-background text-foreground">
<div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md space-y-6">
        <div class="flex items-center justify-center gap-2">
            <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" class="size-9" role="img" aria-label="Taskflow">
                <rect width="32" height="32" rx="8" fill="#2156CA" />
                <g fill="none" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round">
                    <path d="M10 9 V19" />
                    <path d="M16 9 V23" />
                    <path d="M22 9 V15" />
                </g>
            </svg>
            <span class="text-xl font-bold tracking-tight">Task<span class="text-primary">flow</span></span>
        </div>

        <div class="rounded-lg border bg-card text-card-foreground">
            <div class="flex flex-col space-y-1.5 p-6">
                <h1 class="text-xl font-semibold tracking-tight text-center">
                    Authorize {{ $client->name }}
                </h1>
                <p class="text-sm text-muted-foreground text-center">
                    This application will be able to manage your projects, tasks, tags, notes, calendar, and secret vault — the same things you can do in the dashboard.
                </p>
            </div>

            <div class="p-6 pt-0 space-y-4">
                <div class="rounded-lg border p-4 bg-muted/50">
                    <p class="text-sm text-muted-foreground mb-1">Signed in as</p>
                    <p class="font-medium text-sm">{{ $user->email }}</p>
                </div>

                @if(count($scopes) > 0)
                    <div class="space-y-2">
                        <p class="text-sm font-medium">Permissions</p>
                        <ul class="space-y-2">
                            @foreach($scopes as $scope)
                                <li class="flex items-start gap-2">
                                    <span class="mt-1.5 size-1.5 rounded-full bg-primary"></span>
                                    <span class="text-sm text-muted-foreground">{{ $scope->description }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="flex items-center p-6 pt-0 gap-3">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 h-10 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Cancel
                    </button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1" id="authorizeForm">
                    @csrf
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" id="authorizeButton" class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 h-10 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50">
                        <span id="authorizeText">Authorize</span>
                    </button>
                </form>
            </div>
        </div>

        <p class="text-xs text-muted-foreground text-center">
            You can revoke this connection anytime on the MCP page in Taskflow.
        </p>
    </div>
</div>

<script>
    // After approving, the OAuth redirect completes in this popup — close it once done
    document.getElementById('authorizeForm').addEventListener('submit', function () {
        const button = document.getElementById('authorizeButton');
        button.disabled = true;
        document.getElementById('authorizeText').textContent = 'Authorizing…';

        setTimeout(function () {
            const check = setInterval(function () {
                if (!window.location.href.includes('/oauth/authorize')
                    || window.location.search.includes('code=')
                    || window.location.search.includes('error=')) {
                    clearInterval(check);
                    window.close();
                }
            }, 100);
            setTimeout(function () { clearInterval(check); window.close(); }, 5000);
        }, 200);
    });
</script>
</body>
</html>
