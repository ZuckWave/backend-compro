<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderRequest extends FormRequest
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
            'number' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'string', 'max:100', 'unique:work_orders,number'],
            'vehicle_id' => [$required, 'integer', 'exists:vehicles,id'],
            'mechanic_id' => [
                $required,
                'integer',
                Rule::exists('users', 'id')->where('role', 'mekanik'),
                ...($this->user()?->role === 'mekanik' ? [Rule::in([$this->user()->id])] : []),
            ],
            'complaint' => [$required, 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in($this->isMethod('POST') ? ['open', 'in_progress'] : ['open', 'in_progress', 'completed', 'cancelled'])],
            'service_fee' => ['sometimes', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'completed_at' => ['prohibited'],
        ];
    }
}
