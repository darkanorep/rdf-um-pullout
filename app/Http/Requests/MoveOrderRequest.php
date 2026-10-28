<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveOrderRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'remarks' => ['required', 'string'],
            'move_inventory' => 'array|required',
            'move_inventory.*.inventory_id' => 'integer|required|exists:inventories,id',
            'move_inventory.*.quantity' => 'integer|required',
        ];
    }
}
