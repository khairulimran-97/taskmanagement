<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSecretRequest;
use App\Http\Requests\UpdateSecretRequest;
use App\Models\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SecretController extends Controller
{
    /**
     * Display the secret vault
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $secretsQuery = Secret::forUser(Auth::id())->ordered();

        if ($search) {
            $secretsQuery->search($search);
        }

        // Values are decrypted by the model cast. They're sent to the owning,
        // authenticated user over HTTPS so the page can reveal/copy without an
        // extra round-trip — the page keeps them masked until clicked.
        $secrets = $secretsQuery->get()->map(function ($secret) {
            return [
                'id' => $secret->id,
                'name' => $secret->name,
                'type' => $secret->type,
                'value' => $secret->value,
                'notes' => $secret->notes,
                'created_at' => $secret->created_at,
                'updated_at' => $secret->updated_at,
            ];
        });

        return Inertia::render('Secrets/Index', [
            'secrets' => $secrets,
            'search' => $search,
        ]);
    }

    /**
     * JSON list of secrets for the floating quick-vault slideover.
     */
    public function apiList(Request $request): JsonResponse
    {
        $search = $request->input('q', '');

        $secrets = Secret::forUser(Auth::id())
            ->when($search, fn ($query) => $query->search($search))
            ->ordered()
            ->get()
            ->map(function ($secret) {
                return [
                    'id' => $secret->id,
                    'name' => $secret->name,
                    'type' => $secret->type,
                    'value' => $secret->value,
                    'notes' => $secret->notes,
                    'created_at' => $secret->created_at,
                    'updated_at' => $secret->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'secrets' => $secrets,
        ]);
    }

    /**
     * Store a newly created secret
     */
    public function store(StoreSecretRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();
        $validated['type'] = $validated['type'] ?: 'other';

        Secret::create($validated);

        return redirect()->route('secrets.index')
            ->with('success', 'Secret saved successfully.');
    }

    /**
     * Update the specified secret
     */
    public function update(UpdateSecretRequest $request, string $id): RedirectResponse
    {
        $secret = Secret::forUser(Auth::id())->findOrFail($id);
        $validated = $request->validated();
        $validated['type'] = $validated['type'] ?: 'other';

        $secret->update($validated);

        return redirect()->route('secrets.index')
            ->with('success', 'Secret updated successfully.');
    }

    /**
     * Remove the specified secret
     */
    public function destroy(string $id): RedirectResponse
    {
        $secret = Secret::forUser(Auth::id())->findOrFail($id);
        $secret->delete();

        return redirect()->route('secrets.index')
            ->with('success', 'Secret deleted successfully.');
    }
}
