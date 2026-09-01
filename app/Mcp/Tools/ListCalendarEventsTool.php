<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListCalendarEventsTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'List calendar events in a date range. Defaults to the next 30 days when no range is given.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Range start, YYYY-MM-DD. Default: today.'),
            'to' => $schema->string()->description('Range end, YYYY-MM-DD. Default: from + 30 days.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = isset($validated['from']) ? \Illuminate\Support\Carbon::parse($validated['from'])->startOfDay() : now()->startOfDay();
        $to = isset($validated['to']) ? \Illuminate\Support\Carbon::parse($validated['to'])->endOfDay() : $from->copy()->addDays(30)->endOfDay();

        $events = $request->user()->calendarEvents()
            ->where('start_date', '<=', $to)
            ->where(fn ($query) => $query->where('end_date', '>=', $from)->orWhere('start_date', '>=', $from))
            ->orderBy('start_date')
            ->limit(200)
            ->get();

        return Response::json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $events->map(fn ($event) => $this->eventPayload($event))->all(),
        ]);
    }
}
