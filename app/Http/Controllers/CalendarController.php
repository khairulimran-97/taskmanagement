<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class CalendarController extends Controller
{
    /**
     * Display the calendar page.
     */
    public function index(Request $request): Response
    {
        // Get events for the requested date range or current month
        $startDate = $request->input('start')
            ? Carbon::parse($request->input('start'))
            : now()->startOfMonth();

        $endDate = $request->input('end')
            ? Carbon::parse($request->input('end'))
            : now()->endOfMonth();

        $events = CalendarEvent::where('user_id', Auth::id())
            ->dateRange($startDate, $endDate)
            ->orderBy('start_date', 'asc')
            ->get();

        return Inertia::render('Calendar/Index', [
            'availableColors' => CalendarEvent::getAvailableColors(),
            'events' => $events,
        ]);
    }

    /**
     * Get events for calendar (AJAX endpoint)
     */
    public function getEvents(Request $request): JsonResponse
    {
        $start = $request->input('start');
        $end = $request->input('end');

        $query = CalendarEvent::where('user_id', Auth::id());

        // If start and end dates are provided, filter by date range
        if ($start && $end) {
            $startDate = Carbon::parse($start);
            $endDate = Carbon::parse($end);
            $query->dateRange($startDate, $endDate);
        }

        $events = $query->orderBy('start_date', 'asc')->get();

        // Convert to FullCalendar format
        $calendarEvents = $events->map(function ($event) {
            return $event->toFullCalendarArray();
        });

        return response()->json($calendarEvents);
    }

    /**
     * Store a newly created calendar event.
     */
    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        // Handle all day events - set time to start/end of day
        if ($validated['all_day'] ?? false) {
            $validated['start_date'] = Carbon::parse($validated['start_date'])->startOfDay();
            if (isset($validated['end_date'])) {
                $validated['end_date'] = Carbon::parse($validated['end_date'])->endOfDay();
            }
        }

        $event = CalendarEvent::create($validated);

        return redirect()->route('calendar.index')
            ->with('success', 'Event created successfully');
    }

    /**
     * Display the specified calendar event.
     */
    public function show(string $id): JsonResponse
    {
        $event = CalendarEvent::where('user_id', Auth::id())->findOrFail($id);

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'start_date' => $event->start_date->toISOString(),
                'end_date' => $event->end_date?->toISOString(),
                'color' => $event->color,
                'all_day' => $event->all_day,
                'formatted_start_date' => $event->formatted_start_date,
                'formatted_end_date' => $event->formatted_end_date,
                'is_multi_day' => $event->isMultiDay(),
                'duration_in_hours' => $event->getDurationInHours(),
                'duration_in_days' => $event->getDurationInDays(),
                'created_at' => $event->created_at,
                'updated_at' => $event->updated_at,
            ]
        ]);
    }

    /**
     * Update the specified calendar event.
     */
    public function update(UpdateCalendarEventRequest $request, string $id): RedirectResponse
    {
        $event = CalendarEvent::where('user_id', Auth::id())->findOrFail($id);
        $validated = $request->validated();

        // Handle all day events - set time to start/end of day
        if (isset($validated['all_day']) && $validated['all_day']) {
            if (isset($validated['start_date'])) {
                $validated['start_date'] = Carbon::parse($validated['start_date'])->startOfDay();
            }
            if (isset($validated['end_date'])) {
                $validated['end_date'] = Carbon::parse($validated['end_date'])->endOfDay();
            }
        }

        $event->update($validated);

        return redirect()->route('calendar.index')
            ->with('success', 'Event updated successfully');
    }

    /**
     * Remove the specified calendar event.
     */
    public function destroy(string $id): RedirectResponse
    {
        $event = CalendarEvent::where('user_id', Auth::id())->findOrFail($id);
        $event->delete();

        return redirect()->route('calendar.index')
            ->with('success', 'Event deleted successfully');
    }

    /**
     * Update event dates (for drag and drop)
     */
    public function updateDates(Request $request, string $id): RedirectResponse
    {
        $event = CalendarEvent::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'all_day' => 'nullable|boolean',
        ]);

        // Handle all day events
        if ($validated['all_day'] ?? $event->all_day) {
            $validated['start_date'] = Carbon::parse($validated['start_date'])->startOfDay();
            if (isset($validated['end_date'])) {
                $validated['end_date'] = Carbon::parse($validated['end_date'])->endOfDay();
            }
        }

        $event->update($validated);

        return redirect()->route('calendar.index')
            ->with('success', 'Event updated successfully');
    }

    /**
     * Get events for a specific date
     */
    public function getEventsForDate(Request $request): JsonResponse
    {
        $date = $request->input('date');

        if (!$date) {
            return response()->json(['error' => 'Date parameter is required'], 400);
        }

        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        $events = CalendarEvent::where('user_id', Auth::id())
            ->where(function ($query) use ($startOfDay, $endOfDay) {
                $query->whereBetween('start_date', [$startOfDay, $endOfDay])
                    ->orWhereBetween('end_date', [$startOfDay, $endOfDay])
                    ->orWhere(function ($q) use ($startOfDay, $endOfDay) {
                        $q->where('start_date', '<=', $startOfDay)
                            ->where('end_date', '>=', $endOfDay);
                    });
            })
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date->toISOString(),
                    'end_date' => $event->end_date?->toISOString(),
                    'color' => $event->color,
                    'all_day' => $event->all_day,
                    'formatted_start_date' => $event->formatted_start_date,
                    'formatted_end_date' => $event->formatted_end_date,
                ];
            })
        ]);
    }

    /**
     * Get upcoming events (next 7 days)
     */
    public function getUpcomingEvents(): JsonResponse
    {
        $startDate = now()->startOfDay();
        $endDate = now()->addDays(7)->endOfDay();

        $events = CalendarEvent::where('user_id', Auth::id())
            ->dateRange($startDate, $endDate)
            ->orderBy('start_date', 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_date' => $event->start_date->toISOString(),
                    'end_date' => $event->end_date?->toISOString(),
                    'color' => $event->color,
                    'all_day' => $event->all_day,
                    'formatted_start_date' => $event->formatted_start_date,
                    'formatted_end_date' => $event->formatted_end_date,
                    'is_today' => $event->start_date->isToday(),
                    'is_tomorrow' => $event->start_date->isTomorrow(),
                    'days_until' => now()->diffInDays($event->start_date, false),
                ];
            })
        ]);
    }
}
