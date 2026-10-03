<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'project_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('user_id', $this->user()->id),
            ],
            'status' => [
                'sometimes',
                'nullable',
                Rule::in(['pending', 'in_progress', 'completed', 'cancelled']),
            ],
            'priority' => [
                'sometimes',
                'nullable',
                Rule::in(['low', 'medium', 'high', 'urgent']),
            ],
            'due_date_from' => ['sometimes', 'nullable', 'date'],
            'due_date_to' => [
                'sometimes',
                'nullable',
                'date',
                ...($this->filled('due_date_from') ? ['after_or_equal:due_date_from'] : []),
            ],
            'sort_by' => [
                'sometimes',
                Rule::in(['created_at', 'updated_at', 'due_date', 'title', 'priority']),
            ],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
