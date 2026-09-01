<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetSecretTool extends Tool
{
    protected string $description = 'Read one vault secret INCLUDING its decrypted value and notes. Only call this when the user explicitly needs the value; never echo values into logs or summaries unnecessarily.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The secret id from list-secrets.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $secret = $request->user()->secrets()->find($request->get('id'));

        if (! $secret) {
            return Response::error('Secret not found.');
        }

        return Response::json([
            'secret' => [
                'id' => $secret->id,
                'name' => $secret->name,
                'type' => $secret->type,
                'value' => $secret->value,
                'notes' => $secret->notes,
            ],
        ]);
    }
}
