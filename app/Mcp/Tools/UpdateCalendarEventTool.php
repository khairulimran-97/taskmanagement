<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateCalendarEventTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Update a calendar event — retitle, move, resize, recolor. Only the fields you pass change.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The event id.')->required(),
            'title' => $schema->string(),
            'start_date' => $schema->string()->description('e.g. 2026-09-01 14:00.'),
            'end_date' => $schema->string(),
            'description' => $schema->string(),
            'color' => $schema->string()->description('Hex color.'),
            'all_day' => $schema->boolean(),
        ];
    }

    public function handle(Request $request): Response
    {
        $event = $request->user()->calendarEvents()->find($request->get('id'));

        if (! $event) {
            return Response::error('Event not found.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date',
            'description' => 'sometimes|nullable|string|max:5000',
            'color' => 'sometimes|nullable|string|max:7',
            'all_day' => 'sometimes|boolean',
        ]);

        $event->update($validated);

        if ($event->end_date && $event->end_date->lt($event->start_date)) {
            $event->update(['end_date' => $event->start_date]);
        }

        return Response::json(['updated' => $this->eventPayload($event->fresh())]);
    }
}
