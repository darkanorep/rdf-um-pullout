<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MiscellaneousRequest extends FormRequest
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
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'date' => ['required', 'date'],
            'transaction_type' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'miscellaneous_issue' => ['required', 'array'],
            'miscellaneous_issue.*.inventory_id' => ['required', 'integer', 'exists:inventories,id'],
            'miscellaneous_issue.*.quantity' => ['required', 'integer']
        ];
    }

    public function attributes()
    {
        return [
            'warehouse_id' => 'warehouse',
            'date' => 'date',
            'transaction_type' => 'transaction type',
            'remarks' => 'remarks'
        ];
    }
}
