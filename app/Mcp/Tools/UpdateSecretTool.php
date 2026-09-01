<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateSecretTool extends Tool
{
    protected string $description = 'Update a vault secret\'s name, type, value, or notes. Only the fields you pass change.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The secret id.')->required(),
            'name' => $schema->string(),
            'value' => $schema->string()->description('New secret value.'),
            'type' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response
    {
        $secret = $request->user()->secrets()->find($request->get('id'));

        if (! $secret) {
            return Response::error('Secret not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes|string',
            'type' => 'sometimes|nullable|string|max:50',
            'notes' => 'sometimes|nullable|string',
        ]);

        $secret->update($validated);

        return Response::json(['updated' => ['id' => $secret->id, 'name' => $secret->name, 'type' => $secret->type]]);
    }
}
