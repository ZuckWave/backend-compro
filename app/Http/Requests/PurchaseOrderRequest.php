<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
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
            'number' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'string', 'max:100', 'unique:purchase_orders,number'],
            'supplier_id' => [$required, 'integer', 'exists:suppliers,id'],
            'order_date' => [$required, 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => [$required, 'array', 'min:1', 'max:100'],
            'items.*' => ['array:spare_part_id,quantity,unit_price'],
            'items.*.spare_part_id' => ['required', 'integer', 'distinct', 'exists:spare_parts,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'status' => ['prohibited'],
        ];
    }
}
