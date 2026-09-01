<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteTagTool extends Tool
{
    protected string $description = 'Delete a tag. Tasks keep existing — they just lose the tag.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The tag id to delete.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $tag = $request->user()->tags()->find($request->get('id'));

        if (! $tag) {
            return Response::error('Tag not found.');
        }

        $name = $tag->name;
        $tag->tasks()->detach();
        $tag->delete();

        return Response::json(['deleted' => $name]);
    }
}
