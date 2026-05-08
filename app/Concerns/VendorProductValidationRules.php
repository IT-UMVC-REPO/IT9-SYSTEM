<?php

namespace App\Concerns;

use App\Enums\ProductStatus;
use App\Enums\ProductUnit;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait VendorProductValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function vendorProductRules(string $prefix = '', string $imageField = 'productImageUpload', bool $requireImage = true): array
    {
        $qualifiedKey = static fn (string $key): string => $prefix !== '' ? $prefix.'.'.$key : $key;

        return [
            $qualifiedKey('name') => ['required', 'string', 'max:255'],
            $qualifiedKey('description') => ['required', 'string', 'max:1000'],
            $qualifiedKey('price') => ['required', 'numeric', 'min:0.01'],
            $qualifiedKey('stock_quantity') => ['required', 'integer', 'min:0'],
            $qualifiedKey('categoryId') => [
                'required',
                'integer',
                Rule::in(Category::query()->leaves()->pluck('id')->all()),
            ],
            $qualifiedKey('status') => ['required', Rule::enum(ProductStatus::class)],
            $qualifiedKey('unit') => ['required', Rule::enum(ProductUnit::class)],
            $qualifiedKey('base_unit') => ['nullable', 'string', Rule::in(['kg', 'g', 'L', 'ml', ''])],
            $qualifiedKey('base_unit_quantity') => [
                'nullable',
                'numeric',
                'min:0.0001',
                'max:999999',
                'required_with:'.$qualifiedKey('base_unit'),
            ],
            $imageField => array_values(array_filter([
                $requireImage ? 'required' : 'nullable',
                'image',
                'max:3072',
            ])),
        ];
    }
}
