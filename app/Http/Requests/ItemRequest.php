<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
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
            'code' => 'required|string|unique:items,code,' . $this->item?->id,
            'name' => 'required|string|unique:items,name,' . $this->item?->id,
            'slug' => 'nullable|string|unique:items,slug,' . $this->item?->id,
            'uom_id' => 'required|integer|exists:unit_of_measurements,id',
            'category_id' => 'required|integer|exists:categories,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id'
        ];
    }
}
