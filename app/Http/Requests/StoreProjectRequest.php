<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'status' => 'nullable|string|in:active,paused,completed,archived',
            'priority' => 'nullable|string|in:low,medium,high',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'start_date' => 'nullable|date',
            'sort_order' => 'nullable|integer'
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
            'name' => 'project name',
            'due_date' => 'due date',
            'start_date' => 'start date',
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
            'name.required' => 'A project name is required',
            'color.regex' => 'The color must be a valid hex color code (e.g. #3B82F6)',
            'due_date.after_or_equal' => 'The due date must be after or equal to the start date',
        ];
    }
}
