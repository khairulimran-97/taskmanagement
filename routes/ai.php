<?php

use App\Mcp\Servers\TaskflowServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Mcp\Facades\Mcp;

// Dynamic client registration + OAuth discovery — what makes token-less per-user OAuth work
Mcp::oauthRoutes();

Mcp::web('/mcp', TaskflowServer::class)
    ->middleware(['auth:api', 'throttle:120,1']);

// Browsers get a quick-info page; MCP clients keep the spec-mandated 405 on GET
Route::get('/mcp', function (Request $request) {
    if (str_contains($request->header('Accept', ''), 'text/html')) {
        return Inertia::render('McpInfo', [
            'mcpUrl' => url('/mcp'),
            'discoveryUrl' => url('/.well-known/oauth-authorization-server'),
            'resourceMetadataUrl' => url('/.well-known/oauth-protected-resource'),
        ]);
    }

    return response('', 405)->header('Allow', 'POST');
})->middleware('web');
