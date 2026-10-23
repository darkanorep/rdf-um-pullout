<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MiscellaneousReceiptRequest extends FormRequest
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
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'remarks' => ['required', 'string'],
            'miscellaneous_inventory' => 'array|required',
            'miscellaneous_inventory.*.item_id' => 'integer|required|exists:items,id',
            'miscellaneous_inventory.*.uom_id' => 'integer|required|exists:unit_of_measurements,id',
            'miscellaneous_inventory.*.account_title_id' => 'integer|required|exists:account_titles,id',
            'miscellaneous_inventory.*.quantity' => 'integer|required',
        ];
    }
}
