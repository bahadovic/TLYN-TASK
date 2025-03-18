<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|in:buy,sell',
            'amount' => 'required|numeric|min:0.01',
            'price' => 'required|numeric|min:0',
        ];
    }

    public function authorize(): bool
    {
        return false;
    }

}
