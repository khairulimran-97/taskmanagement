<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class McpAccessController extends Controller
{
    /**
     * MCP access page: personal API tokens + authorized OAuth connections.
     */
    public function index(Request $request): Response
    {
        /** @var Collection<int, Token> $tokens */
        $tokens = $request->user()->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();

        // Personal access tokens are the user-managed "API tokens"; every other
        // client is an OAuth app the user authorized (Claude Code, etc.)
        [$apiTokens, $oauthTokens] = $tokens->partition(
            fn (Token $token) => $token->client?->hasGrantType('personal_access')
        );

        $connections = $oauthTokens
            ->groupBy('client_id')
            ->map(fn (Collection $group): array => [
                'client_id' => (string) $group->first()->client_id,
                'name' => $group->first()->client?->name ?: 'Unknown client',
                'authorized_at' => optional($group->min('created_at'))->toIso8601String(),
                'last_activity_at' => optional($group->max('created_at'))->toIso8601String(),
                'tokens' => $group->count(),
            ])
            ->values();

        return Inertia::render('McpAccess', [
            'mcpUrl' => url('/mcp'),
            'infoUrl' => url('/mcp'),
            'apiTokens' => $apiTokens->map(fn (Token $token): array => [
                'id' => $token->id,
                'name' => $token->name ?: 'Unnamed token',
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])->values(),
            'connections' => $connections,
            'plainTextToken' => $request->session()->get('plainTextToken'),
        ]);
    }

    /**
     * Issue a new personal access token; the plaintext is flashed once.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => 'required|string|max:255']);

        $token = $request->user()->createToken($validated['name']);

        return back()->with('plainTextToken', $token->accessToken);
    }

    /**
     * Revoke one personal access token.
     */
    public function destroyToken(Request $request, string $tokenId): RedirectResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->firstOrFail();
        $token->revoke();

        return back()->with('success', 'Token revoked');
    }

    /**
     * Revoke an OAuth client connection: all of its access + refresh tokens.
     */
    public function destroyConnection(Request $request, string $clientId): RedirectResponse
    {
        $tokenIds = $request->user()->tokens()->where('client_id', $clientId)->pluck('id');

        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        $request->user()->tokens()->where('client_id', $clientId)->update(['revoked' => true]);

        return back()->with('success', 'Connection revoked — the app must sign in again to reconnect');
    }
}
