<?php

namespace Modules\Payment\Http\Requests\Dashboard\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutletPayWayRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $outlet = $this->route('outlet');

        $rules = [
            'payway_merchant_id' => ['required', 'string', 'max:30'],
            'payway_enabled' => ['sometimes', 'boolean'],
        ];

        // API key required only for new setup
        if (!$outlet || !$outlet->payway_api_key) {
            $rules['payway_api_key'] = ['required', 'string'];
        } else {
            $rules['payway_api_key'] = ['nullable', 'string'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'payway_merchant_id.required' => 'Merchant ID is required.',
            'payway_merchant_id.max' => 'Merchant ID must not exceed 30 characters.',
            'payway_api_key.required' => 'API key is required for new setup.',
        ];
    }
}
