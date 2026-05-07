<?php

namespace App\Enums;

class TagumCoordinate
{
    public const LAT_MIN = 7.40;

    public const LAT_MAX = 7.48;

    public const LNG_MIN = 125.76;

    public const LNG_MAX = 125.84;

    public const CENTER_LAT = 7.4479;

    public const CENTER_LNG = 125.8090;

    /**
     * @return array{lat: float, lng: float}
     */
    public static function random(): array
    {
        return [
            'lat' => round(self::LAT_MIN + mt_rand(0, 10000) / 10000 * (self::LAT_MAX - self::LAT_MIN), 6),
            'lng' => round(self::LNG_MIN + mt_rand(0, 10000) / 10000 * (self::LNG_MAX - self::LNG_MIN), 6),
        ];
    }
}
