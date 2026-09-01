<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteCalendarEventTool extends Tool
{
    protected string $description = 'Permanently delete a calendar event.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The event id to delete.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $event = $request->user()->calendarEvents()->find($request->get('id'));

        if (! $event) {
            return Response::error('Event not found.');
        }

        $title = $event->title;
        $event->delete();

        return Response::json(['deleted' => $title]);
    }
}
