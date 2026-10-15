<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitOfMeasurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unit = $this->route('unitOfMeasurement');

        return [
            'name'   => ['required', 'string', 'max:255',
                Rule::unique('unit_of_measurements', 'name')->ignore($unit)],
            'symbol' => ['required', 'string', 'max:20',
                Rule::unique('unit_of_measurements', 'symbol')->ignore($unit)],
        ];
    }
}
