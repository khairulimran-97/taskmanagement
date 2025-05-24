<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string $color
 * @property bool $all_day
 * @property int $user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @method static where(string $string, mixed $id)
 * @method static create(array $validated)
 */
class CalendarEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'color',
        'all_day',
        'user_id'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'all_day' => 'boolean',
    ];

    /**
     * Get the user that owns the calendar event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get events for a specific date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($q2) use ($start, $end) {
                    $q2->where('start_date', '<=', $start)
                        ->where('end_date', '>=', $end);
                });
        });
    }

    /**
     * Scope to get events for current month
     */
    public function scopeCurrentMonth($query)
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return $query->dateRange($startOfMonth, $endOfMonth);
    }

    /**
     * Get formatted start date for display
     */
    public function getFormattedStartDateAttribute(): string
    {
        if ($this->all_day) {
            return $this->start_date->format('M j, Y');
        }

        return $this->start_date->format('M j, Y g:i A');
    }

    /**
     * Get formatted end date for display
     */
    public function getFormattedEndDateAttribute(): ?string
    {
        if (!$this->end_date) return null;

        if ($this->all_day) {
            return $this->end_date->format('M j, Y');
        }

        return $this->end_date->format('M j, Y g:i A');
    }

    /**
     * Check if event is all day
     */
    public function isAllDay(): bool
    {
        return $this->all_day;
    }

    /**
     * Check if event spans multiple days
     */
    public function isMultiDay(): bool
    {
        if (!$this->end_date) return false;

        return $this->start_date->format('Y-m-d') !== $this->end_date->format('Y-m-d');
    }

    /**
     * Get duration in hours
     */
    public function getDurationInHours(): ?float
    {
        if (!$this->end_date || $this->all_day) return null;

        return $this->start_date->diffInHours($this->end_date, true);
    }

    /**
     * Get duration in days
     */
    public function getDurationInDays(): ?int
    {
        if (!$this->end_date) return null;

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Convert to FullCalendar format
     */
    public function toFullCalendarArray(): array
    {
        $event = [
            'id' => $this->id,
            'title' => $this->title,
            'start' => $this->start_date->toISOString(),
            'color' => $this->color,
            'allDay' => $this->all_day,
            'extendedProps' => [
                'description' => $this->description,
                'user_id' => $this->user_id,
            ]
        ];

        if ($this->end_date) {
            $event['end'] = $this->end_date->toISOString();
        }

        return $event;
    }

    /**
     * Get all available colors for events
     */
    public static function getAvailableColors(): array
    {
        return [
            '#3B82F6', // Blue
            '#EF4444', // Red
            '#10B981', // Green
            '#F59E0B', // Yellow
            '#8B5CF6', // Purple
            '#F97316', // Orange
            '#06B6D4', // Cyan
            '#84CC16', // Lime
            '#EC4899', // Pink
            '#6B7280', // Gray
        ];
    }
}
