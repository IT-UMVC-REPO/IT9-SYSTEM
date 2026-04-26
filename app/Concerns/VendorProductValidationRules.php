<?php

namespace App\Concerns;

use App\Enums\ProductStatus;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait VendorProductValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function vendorProductRules(bool $requireImage = true): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'categoryId' => [
                'required',
                'integer',
                Rule::in(Category::query()->leaves()->pluck('id')->all()),
            ],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'productImageUpload' => array_values(array_filter([
                $requireImage ? 'required' : 'nullable',
                'image',
                'max:3072',
            ])),
        ];
    }
}
