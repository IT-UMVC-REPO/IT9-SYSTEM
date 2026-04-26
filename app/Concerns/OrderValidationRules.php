<?php

namespace App\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait OrderValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function orderRules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:300'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
