<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShipmentRequest extends FormRequest
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
            'number' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'string', 'max:100', 'unique:shipments,number'],
            'work_order_id' => ['nullable', 'integer', 'exists:work_orders,id'],
            'recipient_name' => [$required, 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => [$required, 'string', 'max:2000'],
            'courier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }
}
