<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), ['after_or_equal:from'])],
            'status' => ['nullable', Rule::in(['draft', 'ordered', 'partial', 'received', 'open', 'in_progress', 'completed', 'pending', 'shipped', 'delivered', 'cancelled'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'low_stock' => ['sometimes', 'boolean'],
        ];
    }
}
