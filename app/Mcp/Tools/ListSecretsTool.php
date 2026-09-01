<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListSecretsTool extends Tool
{
    protected string $description = 'List the user\'s vault secrets — names and types only, never values. Use get-secret to read one.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Match against secret names.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $secrets = $request->user()->secrets()
            ->when($request->get('search'), fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->get();

        return Response::json([
            'secrets' => $secrets->map(fn ($secret) => [
                'id' => $secret->id,
                'name' => $secret->name,
                'type' => $secret->type,
                'has_notes' => filled($secret->notes),
                'updated_at' => $secret->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
