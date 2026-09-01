<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetNoteTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Read one note in full. Content is the editor\'s HTML.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The note id.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $note = $request->user()->notes()->find($request->get('id'));

        if (! $note) {
            return Response::error('Note not found.');
        }

        $note->update(['last_accessed_at' => now()]);

        return Response::json(['note' => $this->notePayload($note, withContent: true)]);
    }
}
