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

            'move_inventory' => ['required', 'array', 'min:1'],
            'move_inventory.*.item_id' => [
                'required',
                'integer',
                'distinct',
                'exists:items,id',
            ],
            'move_inventory.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
