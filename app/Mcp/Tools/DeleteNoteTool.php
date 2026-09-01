<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteNoteTool extends Tool
{
    protected string $description = 'Permanently delete a note. Confirm with the user before calling this.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The note id to delete.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $note = $request->user()->notes()->find($request->get('id'));

        if (! $note) {
            return Response::error('Note not found.');
        }

        $title = $note->title;
        $note->delete();

        return Response::json(['deleted' => $title]);
    }
}
