<?php

namespace App\Support;

class BrandColorConverter
{
    public static function toOklchCssVars(string $hex): string
    {
        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;

        $lin = fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $rl = $lin($r);
        $gl = $lin($g);
        $bl = $lin($b);

        $X = 0.4122214708 * $rl + 0.5363325363 * $gl + 0.0514459929 * $bl;
        $Y = 0.2119034982 * $rl + 0.6806995451 * $gl + 0.1073969566 * $bl;
        $Z = 0.0883024619 * $rl + 0.2817188376 * $gl + 0.6299787005 * $bl;

        $cbrt = fn ($v) => $v >= 0 ? $v ** (1 / 3) : -((-$v) ** (1 / 3));
        $l_ = $cbrt($X);
        $m_ = $cbrt($Y);
        $s_ = $cbrt($Z);

        $L = 0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_;
        $a = 1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_;
        $bv = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_;

        $C = sqrt($a ** 2 + $bv ** 2);
        $H = fmod(rad2deg(atan2($bv, $a)) + 360, 360);

        $cl = fn ($v) => max(0.05, min(0.98, $v));
        $cc = fn ($v) => max(0.0, min(0.37, $v));
        $f = fn ($l, $c) => sprintf('oklch(%.4f %.4f %.2f)', $cl($l), $cc($c), $H);

        $stops = [
            50 => $f(0.97, $C * 0.25),
            100 => $f(0.93, $C * 0.35),
            200 => $f(0.87, $C * 0.45),
            300 => $f(0.79, $C * 0.60),
            400 => $f(0.70, $C * 0.75),
            500 => $f($L, $C),
            600 => $f($L * 0.82, $C * 1.05),
            700 => $f($L * 0.68, $C * 1.08),
            800 => $f($L * 0.52, $C * 0.95),
            900 => $f($L * 0.36, $C * 0.80),
            950 => $f($L * 0.22, $C * 0.60),
        ];

        $vars = [];

        foreach ($stops as $stop => $value) {
            $vars[] = "--brand-{$stop}:{$value}";
        }

        $vars[] = '--color-accent:var(--brand-600)';
        $vars[] = '--color-accent-content:var(--brand-700)';
        $vars[] = '--color-accent-foreground:#ffffff';

        return implode(';', $vars);
    }
}
