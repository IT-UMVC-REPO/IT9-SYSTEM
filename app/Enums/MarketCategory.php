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
        $photoIds = [
            self::Vegetables->value => 'photo-1540420773420-3366772f4999',
            self::VegetablesLeafyGreens->value => 'photo-1576045057995-568f588f82fb',
            self::VegetablesRootCrops->value => 'photo-1598170845058-32b9d6a5da37',
            self::VegetablesFruitVegetables->value => 'photo-1563565375-f3fdfdbefa83',
            self::Fruits->value => 'photo-1519996529931-28324d5a630e',
            self::FruitsTropical->value => 'photo-1546548970-71785318a17b',
            self::FruitsCitrus->value => 'photo-1587735243615-c03f25aaff15',
            self::FruitsBananas->value => 'photo-1528825871115-3581a5387919',
            self::Seafood->value => 'photo-1510130387422-82bed34b37e9',
            self::SeafoodFreshFish->value => 'photo-1580822184713-fc5400e7fe10',
            self::SeafoodShellfish->value => 'photo-1565680018434-b513d5e5fd47',
            self::SeafoodCrustaceans->value => 'photo-1559737558-2f5a35f4523b',
            self::MeatAndPoultry->value => 'photo-1607623814075-e51df1bdc82f',
            self::MeatAndPoultryPork->value => 'photo-1602470520998-f4a52199a3d6',
            self::MeatAndPoultryBeef->value => 'photo-1558030006-450675393462',
            self::MeatAndPoultryChicken->value => 'photo-1604503468506-a8da13d11d36',
            self::Grains->value => 'photo-1586201375761-83865001e31c',
            self::GrainsRice->value => 'photo-1536304993881-ff6e9eefa2a6',
            self::GrainsCornAndFlour->value => 'photo-1551754655-cd27e38d2076',
            self::DairyAndEggs->value => 'photo-1607863680198-23d4b2565df0',
            self::DairyAndEggsEggs->value => 'photo-1518569656558-1f25e69d2221',
            self::DairyAndEggsMilkAndDairy->value => 'photo-1550583724-b2692b85b150',
            self::SpicesCondimentsAndOils->value => 'photo-1596040033229-a9821ebd058d',
            self::SpicesCondimentsAndOilsFreshAromatics->value => 'photo-1540553016722-983e48a2cd10',
            self::SpicesCondimentsAndOilsCondimentsAndSauces->value => 'photo-1563805042-7684c019e1cb',
            self::SpicesCondimentsAndOilsCookingOils->value => 'photo-1474979266404-7eaacbcd87c5',
            self::DriedAndSaltedGoods->value => 'photo-1589881133595-a3c085cb731d',
            self::DriedAndSaltedGoodsDriedFish->value => 'photo-1504674900247-0877df9cc836',
            self::DriedAndSaltedGoodsSmokedAndFermentedGoods->value => 'photo-1601050690597-df0568f70950',
            self::KakaninAndNativeSweets->value => 'photo-1563805042-7684c019e1cb',
            self::KakaninAndNativeSweetsSteamedKakanin->value => 'photo-1578985545062-69928b1d9587',
            self::KakaninAndNativeSweetsNativeDelicacies->value => 'photo-1567620905732-2d1ec7ab7445',
            self::FrozenAndProcessedGoods->value => 'photo-1584568694244-14fbdf83bd30',
            self::FrozenAndProcessedGoodsCuredMeats->value => 'photo-1530554764233-e79e16c91d08',
            self::FrozenAndProcessedGoodsFrozenReadyToCook->value => 'photo-1585325701165-8a37e04f9ef1',
        ];

        return sprintf(
            'https://images.unsplash.com/%s?w=640&h=640&fit=crop&auto=format',
            $photoIds[$this->value],
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
