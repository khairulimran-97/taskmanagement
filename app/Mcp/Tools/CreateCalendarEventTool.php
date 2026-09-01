<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use App\Models\CalendarEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateCalendarEventTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Create a calendar event. Times are the user\'s local time; set all_day true for date-only events.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Event title.')->required(),
            'start_date' => $schema->string()->description('Start, e.g. 2026-09-01 14:00 (or YYYY-MM-DD for all-day).')->required(),
            'end_date' => $schema->string()->description('End; defaults to the start.'),
            'description' => $schema->string(),
            'color' => $schema->string()->description('Hex color, e.g. #3B82F6.'),
            'all_day' => $schema->boolean(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:5000',
            'color' => 'nullable|string|max:7',
            'all_day' => 'nullable|boolean',
        ]);

        $validated['end_date'] ??= $validated['start_date'];
        $validated['user_id'] = $request->user()->id;

        $event = CalendarEvent::create($validated);

        return Response::json(['created' => $this->eventPayload($event)]);
    }
}
