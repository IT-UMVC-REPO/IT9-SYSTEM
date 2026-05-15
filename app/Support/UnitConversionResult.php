<?php

namespace App\Support;

use App\Enums\ProductUnit;

readonly class UnitConversionResult
{
    public string $displayString;

    public ?float $pricePerConversionUnit;

    public function __construct(
        public ProductUnit $fromUnit,
        public float $fromQuantity,
        public ProductUnit $toUnit,
        public float $toQuantity,
        ?float $fromUnitPrice = null,
        public ?string $locale = null,
    ) {
        $this->displayString = $this->conversionDisplayString();
        $this->pricePerConversionUnit = $fromUnitPrice !== null && $this->toQuantity > 0
            ? ($fromUnitPrice * $this->fromQuantity) / $this->toQuantity
            : null;
    }

    public function conversionDisplayString(): string
    {
        return UnitFormatter::format($this->fromUnit, $this->fromQuantity, $this->locale)
            .' = '
            .UnitFormatter::format($this->toUnit, $this->toQuantity, $this->locale);
    }

    public function convertedQuantityLabel(): string
    {
        return UnitFormatter::format($this->toUnit, $this->toQuantity, $this->locale);
    }

    public function pricePerConversionUnitLabel(): ?string
    {
        if ($this->pricePerConversionUnit === null) {
            return null;
        }

        return UnitFormatter::pricePerUnit($this->toUnit, $this->pricePerConversionUnit, $this->locale);
    }
}
