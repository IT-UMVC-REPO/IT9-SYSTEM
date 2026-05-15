<?php

namespace App\Enums;

use InvalidArgumentException;

enum ProductUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'g';
    case Pound = 'lb';
    case Ounce = 'oz';
    case Liter = 'L';
    case Milliliter = 'ml';
    case Piece = 'piece';
    case Dozen = 'dozen';
    case Pair = 'pair';
    case Bundle = 'bundle';
    case Pack = 'pack';
    case Bag = 'bag';
    case Tray = 'tray';
    case Bottle = 'bottle';
    case Can = 'can';
    case Box = 'box';
    case Sack = 'sack';
    case Bilao = 'bilao';

    public function label(): string
    {
        return match ($this) {
            self::Kilogram => 'Kilogram',
            self::Gram => 'Gram',
            self::Pound => 'Pound',
            self::Ounce => 'Ounce',
            self::Liter => 'Liter',
            self::Milliliter => 'Milliliter',
            self::Piece => 'Piece',
            self::Dozen => 'Dozen',
            self::Pair => 'Pair',
            self::Bundle => 'Bundle',
            self::Pack => 'Pack',
            self::Bag => 'Bag',
            self::Tray => 'Tray',
            self::Bottle => 'Bottle',
            self::Can => 'Can',
            self::Box => 'Box',
            self::Sack => 'Sack',
            self::Bilao => 'Bilao',
        };
    }

    public function abbreviation(): string
    {
        return $this->symbol();
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Kilogram => 'kg',
            self::Gram => 'g',
            self::Pound => 'lb',
            self::Ounce => 'oz',
            self::Liter => 'L',
            self::Milliliter => 'mL',
            self::Piece => 'pc',
            self::Dozen => 'doz',
            self::Pair => 'pair',
            self::Bundle => 'bundle',
            self::Pack => 'pack',
            self::Bag => 'bag',
            self::Tray => 'tray',
            self::Bottle => 'bottle',
            self::Can => 'can',
            self::Box => 'box',
            self::Sack => 'sack',
            self::Bilao => 'bilao',
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::Kilogram, self::Gram, self::Pound, self::Ounce => UnitType::Weight->value,
            self::Liter, self::Milliliter => UnitType::Volume->value,
            default => UnitType::Count->value,
        };
    }

    public function baseUnit(): ?self
    {
        return match ($this->type()) {
            UnitType::Weight->value => self::Gram,
            UnitType::Volume->value => self::Milliliter,
            default => null,
        };
    }

    public function conversionFactor(): ?float
    {
        return match ($this) {
            self::Kilogram => 1000.0,
            self::Gram => 1.0,
            self::Pound => 453.592,
            self::Ounce => 28.3495,
            self::Liter => 1000.0,
            self::Milliliter => 1.0,
            default => null,
        };
    }

    public function countFactor(): ?int
    {
        return match ($this) {
            self::Dozen => 12,
            self::Pair => 2,
            default => null,
        };
    }

    public function isConvertibleTo(self $other): bool
    {
        return $this->type() === $other->type()
            && $this->conversionFactor() !== null
            && $other->conversionFactor() !== null;
    }

    public function convert(float $quantity, self $targetUnit): float
    {
        if (! $this->isConvertibleTo($targetUnit)) {
            throw new InvalidArgumentException("Cannot convert {$this->value} to {$targetUnit->value}.");
        }

        return ($quantity * $this->conversionFactor()) / $targetUnit->conversionFactor();
    }

    public function isWeightBased(): bool
    {
        return $this->type() === UnitType::Weight->value;
    }

    public function isVolumeBased(): bool
    {
        return $this->type() === UnitType::Volume->value;
    }

    public function isCountBased(): bool
    {
        return $this->type() === UnitType::Count->value;
    }
}
