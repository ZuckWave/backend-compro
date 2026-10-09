<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockOpnameRequest extends FormRequest
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
            'number' => ['nullable', 'string', 'max:100', 'unique:stock_opnames,number'],
            'opname_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:spare_part_id,physical_stock,system_stock,notes'],
            'items.*.spare_part_id' => ['required', 'integer', 'distinct', 'exists:spare_parts,id'],
            'items.*.physical_stock' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'items.*.system_stock' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
