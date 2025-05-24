<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTaskRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:todo,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'start_date' => 'nullable|date',
            'project_id' => 'required|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'sort_order' => 'nullable|integer',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'new_tags' => 'nullable|array',
            'new_tags.*' => 'string|max:255',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'task title',
            'due_date' => 'due date',
            'start_date' => 'start date',
            'project_id' => 'project',
            'assigned_to' => 'assigned user',
            'parent_task_id' => 'parent task',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A task title is required',
            'due_date.after_or_equal' => 'The due date must be after or equal to the start date',
            'project_id.required' => 'A project must be selected',
            'project_id.exists' => 'The selected project does not exist',
        ];
    }
}
