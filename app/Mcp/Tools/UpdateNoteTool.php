<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateNoteTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Update a note\'s title, content, tags, or pinned state. Only the fields you pass change; passing tags replaces the whole tag list.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The note id.')->required(),
            'title' => $schema->string(),
            'content' => $schema->string()->description('Full replacement body, plain text or HTML.'),
            'tags' => $schema->array()->items($schema->string())->description('Full replacement tag list; [] clears tags.'),
            'is_pinned' => $schema->boolean(),
        ];
    }

    public function handle(Request $request): Response
    {
        $note = $request->user()->notes()->find($request->get('id'));

        if (! $note) {
            return Response::error('Note not found.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|nullable|string',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'is_pinned' => 'sometimes|boolean',
        ]);

        if (array_key_exists('tags', $validated)) {
            $validated['tags'] = implode(', ', array_filter(array_map('trim', $validated['tags'])));
        }

        $note->update($validated);

        return Response::json(['updated' => $this->notePayload($note->fresh())]);
    }
}
