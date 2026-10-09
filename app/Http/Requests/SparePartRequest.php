<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SparePartRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'max:100', Rule::unique('spare_parts', 'code')->ignore($this->route('spare_part'))],
            'name' => [$required, 'string', 'max:255'],
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'unit' => [$required, 'string', 'max:50'],
            'purchase_price' => [$required, 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'selling_price' => [$required, 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'min_stock' => ['sometimes', 'integer', 'min:0', 'max:1000000000'],
            'opening_stock' => [$this->isMethod('POST') ? 'sometimes' : 'prohibited', 'integer', 'min:0', 'max:1000000000'],
            'stock' => ['prohibited'],
        ];
    }
}
