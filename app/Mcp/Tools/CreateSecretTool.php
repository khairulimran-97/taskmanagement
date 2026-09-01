<?php

namespace App\Mcp\Tools;

use App\Models\Secret;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateSecretTool extends Tool
{
    protected string $description = 'Store a new secret in the vault (encrypted at rest).';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Secret name, e.g. "GitHub PAT".')->required(),
            'value' => $schema->string()->description('The secret value.')->required(),
            'type' => $schema->string()->description('Free-form type label, e.g. password, api_token, ssh_key.'),
            'notes' => $schema->string()->description('Context notes (also encrypted).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'required|string',
            'type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $secret = Secret::create($validated + ['user_id' => $request->user()->id]);

        return Response::json(['created' => ['id' => $secret->id, 'name' => $secret->name, 'type' => $secret->type]]);
    }
}
