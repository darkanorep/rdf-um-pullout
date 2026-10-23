<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
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
            'received_date' => 'date|required',
            'supplier_name' => 'string|required',
            'remarks' => 'string|nullable',
            'inventory' => 'array|required',
            'inventory.*.item_id' => 'integer|required|exists:items,id',
            'inventory.*.uom_id' => 'integer|required|exists:unit_of_measurements,id',
            'inventory.*.quantity_received' => 'integer|required',
            'inventory.*.actual_received' => 'integer|required',
            'inventory.*.account_title_id' => 'integer|required|exists:account_titles,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'inventory.*.item_id' => 'item',
            'inventory.*.uom_id' => 'uom',
        ];
    }
}
