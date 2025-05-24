<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCalendarEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = CalendarEvent::findOrFail($this->route('calendar_event'));
        return Auth::check() && $event->user_id === Auth::id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'all_day' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'event title',
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'An event title is required',
            'start_date.required' => 'A start date is required',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date',
            'color.regex' => 'The color must be a valid hex color code (e.g. #3B82F6)',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert all_day to boolean if present
        if ($this->has('all_day')) {
            $this->merge([
                'all_day' => filter_var($this->all_day, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
