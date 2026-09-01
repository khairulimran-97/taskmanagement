<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteSecretTool extends Tool
{
    protected string $description = 'Permanently delete a vault secret. This cannot be undone — confirm with the user first.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The secret id to delete.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $secret = $request->user()->secrets()->find($request->get('id'));

        if (! $secret) {
            return Response::error('Secret not found.');
        }

        $name = $secret->name;
        $secret->delete();

        return Response::json(['deleted' => $name]);
    }
}
