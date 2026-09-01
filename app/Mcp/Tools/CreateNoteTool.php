<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateNoteTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Create a note. Content may be plain text or HTML (the notes editor is rich text).';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Note title.')->required(),
            'content' => $schema->string()->description('Body, plain text or HTML.'),
            'tags' => $schema->array()->items($schema->string())->description('Free-form tag labels.'),
            'is_pinned' => $schema->boolean()->description('Pin the note to the top of the list.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_pinned' => 'nullable|boolean',
        ]);

        // Notes store tags as a comma-separated string (see Note::tags_array)
        if (isset($validated['tags'])) {
            $validated['tags'] = implode(', ', array_filter(array_map('trim', $validated['tags'])));
        }

        $note = Note::create($validated + ['user_id' => $request->user()->id]);

        return Response::json(['created' => $this->notePayload($note)]);
    }
}
