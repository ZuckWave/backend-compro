<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
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
            'owner_name' => [$required, 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'plate_number' => [$required, 'string', 'max:30', Rule::unique('vehicles', 'plate_number')->ignore($this->route('vehicle'))],
            'brand' => [$required, 'string', 'max:100'],
            'type' => [$required, 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
        ];
    }
}
