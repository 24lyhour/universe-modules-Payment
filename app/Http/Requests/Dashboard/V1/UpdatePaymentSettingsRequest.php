<?php

namespace Modules\Payment\Http\Requests\Dashboard\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingsRequest extends FormRequest
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
        return [
            'wallet_enabled' => ['sometimes', 'boolean'],
            'cash_enabled' => ['sometimes', 'boolean'],
            'aba_payway_enabled' => ['sometimes', 'boolean'],
            'credit_card_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
