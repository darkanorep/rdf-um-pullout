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
            'stock_id' => ['required', 'integer', 'exists:stocks,id'],
            'transaction_date' => ['required', 'date'],
            'miscellaneous_receipt_inventory' => 'array|required',
            'miscellaneous_receipt_inventory.*.inventory_id' => 'integer|required|exists:inventories,id',
            'miscellaneous_receipt_inventory.*.quantity' => 'integer|required',
            'miscellaneous_receipt_inventory.*.account_title_id' => 'integer|required|exists:account_titles,id',
            'miscellaneous_receipt_inventory.*.remarks' => 'nullable|required',

        ];
    }

    public function attributes(): array
    {
        return [
            'stock_id' => 'Stock',
        ];
    }
}
