<?php

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * @property mixed $is_pinned
 * @property mixed $is_auto_save
 * @property mixed $tags
 */
class UpdateNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $note = Note::find($this->route('note'));
        return $note && $note->user_id === Auth::id();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_pinned' => 'nullable|boolean',
            'is_auto_save' => 'nullable|boolean',
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
            'title' => 'note title',
            'content' => 'note content',
            'tags' => 'tags',
            'is_pinned' => 'pin status',
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
            'title.max' => 'The note title cannot exceed 255 characters',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert is_pinned to boolean if present
        if ($this->has('is_pinned')) {
            $this->merge([
                'is_pinned' => filter_var($this->is_pinned, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Convert is_auto_save to boolean if present
        if ($this->has('is_auto_save')) {
            $this->merge([
                'is_auto_save' => filter_var($this->is_auto_save, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Clean up tags array if present
        if ($this->has('tags') && is_array($this->tags)) {
            $cleanTags = array_filter(
                array_map('trim', $this->tags),
                function ($tag) {
                    return !empty($tag);
                }
            );

            $this->merge([
                'tags' => array_values($cleanTags),
            ]);
        }
    }

    /**
     * Check if this is an auto-save request
     */
    public function isAutoSave(): bool
    {
        return $this->boolean('is_auto_save', false);
    }
}
