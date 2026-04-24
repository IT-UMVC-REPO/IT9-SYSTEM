<?php

namespace App\Enums;

enum MarketCategory: string
{
    case Vegetables = 'vegetables';
    case VegetablesLeafyGreens = 'leafy-greens';
    case VegetablesRootCrops = 'root-crops';
    case VegetablesFruitVegetables = 'fruit-vegetables';
    case Fruits = 'fruits';
    case FruitsTropical = 'tropical-fruits';
    case FruitsCitrus = 'citrus-fruits';
    case FruitsBananas = 'bananas-plantains';
    case Seafood = 'seafood';
    case SeafoodFreshFish = 'fresh-fish';
    case SeafoodShellfish = 'shellfish';
    case SeafoodCrustaceans = 'crustaceans';
    case MeatAndPoultry = 'meat-poultry';
    case MeatAndPoultryPork = 'pork';
    case MeatAndPoultryBeef = 'beef';
    case MeatAndPoultryChicken = 'chicken';
    case Grains = 'grains';
    case GrainsRice = 'rice';
    case GrainsCornAndFlour = 'corn-flour';
    case DairyAndEggs = 'dairy-eggs';
    case DairyAndEggsEggs = 'eggs';
    case DairyAndEggsMilkAndDairy = 'milk-dairy';
    case SpicesCondimentsAndOils = 'spices-condiments-oils';
    case SpicesCondimentsAndOilsFreshAromatics = 'fresh-aromatics';
    case SpicesCondimentsAndOilsCondimentsAndSauces = 'condiments-sauces';
    case SpicesCondimentsAndOilsCookingOils = 'cooking-oils';
    case DriedAndSaltedGoods = 'dried-salted-goods';
    case DriedAndSaltedGoodsDriedFish = 'dried-fish';
    case DriedAndSaltedGoodsSmokedAndFermentedGoods = 'smoked-fermented-goods';
    case KakaninAndNativeSweets = 'kakanin-native-sweets';
    case KakaninAndNativeSweetsSteamedKakanin = 'steamed-kakanin';
    case KakaninAndNativeSweetsNativeDelicacies = 'native-delicacies';
    case FrozenAndProcessedGoods = 'frozen-processed-goods';
    case FrozenAndProcessedGoodsCuredMeats = 'cured-meats';
    case FrozenAndProcessedGoodsFrozenReadyToCook = 'frozen-ready-to-cook';

    public function label(): string
    {
        return match ($this) {
            self::Vegetables => 'Vegetables',
            self::VegetablesLeafyGreens => 'Leafy Greens',
            self::VegetablesRootCrops => 'Root Crops',
            self::VegetablesFruitVegetables => 'Fruit Vegetables',
            self::Fruits => 'Fruits',
            self::FruitsTropical => 'Tropical Fruits',
            self::FruitsCitrus => 'Citrus Fruits',
            self::FruitsBananas => 'Bananas & Plantains',
            self::Seafood => 'Seafood',
            self::SeafoodFreshFish => 'Fresh Fish',
            self::SeafoodShellfish => 'Shellfish',
            self::SeafoodCrustaceans => 'Crustaceans',
            self::MeatAndPoultry => 'Meat & Poultry',
            self::MeatAndPoultryPork => 'Pork',
            self::MeatAndPoultryBeef => 'Beef',
            self::MeatAndPoultryChicken => 'Chicken',
            self::Grains => 'Grains',
            self::GrainsRice => 'Rice',
            self::GrainsCornAndFlour => 'Corn & Flour',
            self::DairyAndEggs => 'Dairy & Eggs',
            self::DairyAndEggsEggs => 'Eggs',
            self::DairyAndEggsMilkAndDairy => 'Milk & Dairy',
            self::SpicesCondimentsAndOils => 'Spices, Condiments & Oils',
            self::SpicesCondimentsAndOilsFreshAromatics => 'Fresh Aromatics',
            self::SpicesCondimentsAndOilsCondimentsAndSauces => 'Condiments & Sauces',
            self::SpicesCondimentsAndOilsCookingOils => 'Cooking Oils',
            self::DriedAndSaltedGoods => 'Dried & Salted Goods',
            self::DriedAndSaltedGoodsDriedFish => 'Dried Fish',
            self::DriedAndSaltedGoodsSmokedAndFermentedGoods => 'Smoked & Fermented Goods',
            self::KakaninAndNativeSweets => 'Kakanin & Native Sweets',
            self::KakaninAndNativeSweetsSteamedKakanin => 'Steamed Kakanin',
            self::KakaninAndNativeSweetsNativeDelicacies => 'Native Delicacies',
            self::FrozenAndProcessedGoods => 'Frozen & Processed Goods',
            self::FrozenAndProcessedGoodsCuredMeats => 'Cured Meats',
            self::FrozenAndProcessedGoodsFrozenReadyToCook => 'Frozen Ready-to-Cook',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Vegetables => 'Fresh market vegetables organized by their most common aisle groups.',
            self::VegetablesLeafyGreens => 'Malunggay, pechay, kangkong, lettuce, and other leafy favorites.',
            self::VegetablesRootCrops => 'Camote, gabi, carrots, radish, and other hearty root crops.',
            self::VegetablesFruitVegetables => 'Talong, ampalaya, kalabasa, okra, and similar market staples.',
            self::Fruits => 'Fresh fruits commonly sold by season and origin in Philippine markets.',
            self::FruitsTropical => 'Mangoes, papaya, pineapple, and other tropical fruit selections.',
            self::FruitsCitrus => 'Calamansi, dalandan, oranges, and other bright citrus fruits.',
            self::FruitsBananas => 'Bananas and plantains for snacking, cooking, and merienda staples.',
            self::Seafood => 'Fresh seafood grouped by the most recognizable wet-market sections.',
            self::SeafoodFreshFish => 'Whole fish, fillets, and cleaned fresh-catch staples.',
            self::SeafoodShellfish => 'Tahong, halaan, oysters, and other shellfish favorites.',
            self::SeafoodCrustaceans => 'Shrimp, crabs, and crustaceans for daily market cooking.',
            self::MeatAndPoultry => 'Fresh-cut meat and poultry grouped by the most common butcher sections.',
            self::MeatAndPoultryPork => 'Daily-cut pork selections for stews, grilling, and soups.',
            self::MeatAndPoultryBeef => 'Fresh beef cuts for sabaw, nilaga, tapa, and more.',
            self::MeatAndPoultryChicken => 'Chicken cuts and poultry staples for everyday meals.',
            self::Grains => 'Core staple grains sold in markets for cooking and baking.',
            self::GrainsRice => 'Rice varieties for daily meals, bulk buying, and pantry refills.',
            self::GrainsCornAndFlour => 'Corn, flour, and grain-based staples for cooking and baking.',
            self::DairyAndEggs => 'Eggs and dairy essentials often stocked in market annexes.',
            self::DairyAndEggsEggs => 'Fresh eggs sold by tray, half-tray, or quick refill quantities.',
            self::DairyAndEggsMilkAndDairy => 'Milk, butter, cheese, and other chilled dairy essentials.',
            self::SpicesCondimentsAndOils => 'Flavor-builders and pantry seasonings for classic Filipino cooking.',
            self::SpicesCondimentsAndOilsFreshAromatics => 'Garlic, onions, ginger, and other fresh aromatics.',
            self::SpicesCondimentsAndOilsCondimentsAndSauces => 'Soy sauce, vinegar, fish sauce, and cooking sauces.',
            self::SpicesCondimentsAndOilsCookingOils => 'Cooking oils and fry-ready pantry essentials.',
            self::DriedAndSaltedGoods => 'Preserved seafood and salty staples common in local markets.',
            self::DriedAndSaltedGoodsDriedFish => 'Daing, tuyo, and other dried fish favorites.',
            self::DriedAndSaltedGoodsSmokedAndFermentedGoods => 'Tinapa, bagoong, and fermented market staples.',
            self::KakaninAndNativeSweets => 'Traditional Filipino kakanin and local sweet delicacies.',
            self::KakaninAndNativeSweetsSteamedKakanin => 'Puto, kutsinta, sapin-sapin, and steamed rice treats.',
            self::KakaninAndNativeSweetsNativeDelicacies => 'Bibingka, suman, kalamay, and native market sweets.',
            self::FrozenAndProcessedGoods => 'Convenience-ready staples sold in freezer and chilled market sections.',
            self::FrozenAndProcessedGoodsCuredMeats => 'Longganisa, tocino, tapa, and other cured meat staples.',
            self::FrozenAndProcessedGoodsFrozenReadyToCook => 'Frozen ready-to-cook items for fast family meals.',
        };
    }

    public function imageUrl(): string
    {
        return sprintf(
            'https://placehold.co/640x640/png?text=%s',
            rawurlencode($this->label()),
        );
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::Vegetables,
            self::Fruits,
            self::Seafood,
            self::MeatAndPoultry,
            self::Grains,
            self::DairyAndEggs,
            self::SpicesCondimentsAndOils,
            self::DriedAndSaltedGoods,
            self::KakaninAndNativeSweets,
            self::FrozenAndProcessedGoods => null,
            self::VegetablesLeafyGreens,
            self::VegetablesRootCrops,
            self::VegetablesFruitVegetables => self::Vegetables,
            self::FruitsTropical,
            self::FruitsCitrus,
            self::FruitsBananas => self::Fruits,
            self::SeafoodFreshFish,
            self::SeafoodShellfish,
            self::SeafoodCrustaceans => self::Seafood,
            self::MeatAndPoultryPork,
            self::MeatAndPoultryBeef,
            self::MeatAndPoultryChicken => self::MeatAndPoultry,
            self::GrainsRice,
            self::GrainsCornAndFlour => self::Grains,
            self::DairyAndEggsEggs,
            self::DairyAndEggsMilkAndDairy => self::DairyAndEggs,
            self::SpicesCondimentsAndOilsFreshAromatics,
            self::SpicesCondimentsAndOilsCondimentsAndSauces,
            self::SpicesCondimentsAndOilsCookingOils => self::SpicesCondimentsAndOils,
            self::DriedAndSaltedGoodsDriedFish,
            self::DriedAndSaltedGoodsSmokedAndFermentedGoods => self::DriedAndSaltedGoods,
            self::KakaninAndNativeSweetsSteamedKakanin,
            self::KakaninAndNativeSweetsNativeDelicacies => self::KakaninAndNativeSweets,
            self::FrozenAndProcessedGoodsCuredMeats,
            self::FrozenAndProcessedGoodsFrozenReadyToCook => self::FrozenAndProcessedGoods,
        };
    }

    public function isLeaf(): bool
    {
        return $this->parent() !== null;
    }

    /**
     * @return list<self>
     */
    public static function topLevelCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category): bool => $category->parent() === null,
        ));
    }

    /**
     * @return list<self>
     */
    public static function leafCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category): bool => $category->isLeaf(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function leafValues(): array
    {
        return array_map(
            fn (self $category): string => $category->value,
            self::leafCases(),
        );
    }
}
