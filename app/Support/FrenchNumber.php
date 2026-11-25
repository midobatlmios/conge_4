<?php

namespace App\Support;

/**
 * Writes an amount in French words for attestations ("quinze mille deux
 * cents dirhams"). The intl extension is not required.
 */
class FrenchNumber
{
    private const UNITS = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    /** "12 500,50" → "douze mille cinq cents dirhams et cinquante centimes". */
    public static function dirhams(float $amount): string
    {
        $whole = (int) floor($amount);
        $cents = (int) round(($amount - $whole) * 100);

        $text = self::words($whole).' dirham'.($whole > 1 ? 's' : '');

        return $cents > 0 ? "{$text} et ".self::words($cents).' centime'.($cents > 1 ? 's' : '') : $text;
    }

    public static function words(int $number): string
    {
        if ($number < 0) {
            return 'moins '.self::words(-$number);
        }

        if ($number < 1000) {
            return self::belowThousand($number, true);
        }

        $parts = [];
        foreach ([1_000_000_000 => 'milliard', 1_000_000 => 'million'] as $size => $name) {
            if ($number >= $size) {
                $count = intdiv($number, $size);
                $parts[] = self::belowThousand($count, false).' '.$name.($count > 1 ? 's' : '');
                $number %= $size;
            }
        }

        if ($number >= 1000) {
            $thousands = intdiv($number, 1000);
            // "mille" is invariable and takes no "un" before it.
            $parts[] = $thousands === 1 ? 'mille' : self::belowThousand($thousands, false).' mille';
            $number %= 1000;
        }

        if ($number > 0) {
            $parts[] = self::belowThousand($number, true);
        }

        return implode(' ', $parts);
    }

    /** @param bool $final  Last group of the number: "cents"/"vingts" take an s only there. */
    private static function belowThousand(int $number, bool $final): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;
        $parts = [];

        if ($hundreds > 0) {
            $word = $hundreds === 1 ? 'cent' : self::UNITS[$hundreds].' cent';
            $parts[] = $hundreds > 1 && $rest === 0 && $final ? $word.'s' : $word;
        }

        if ($rest > 0 || $hundreds === 0) {
            $parts[] = self::belowHundred($rest, $final);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $number, bool $final): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }

        $tens = intdiv($number, 10);
        $unit = $number % 10;

        // 70–79 and 90–99 are built on soixante and quatre-vingt.
        if ($tens === 7 || $tens === 9) {
            $base = $tens === 7 ? 'soixante' : 'quatre-vingt';
            $teen = self::UNITS[10 + $unit];

            return $tens === 7 && $unit === 1 ? 'soixante et onze' : "{$base}-{$teen}";
        }

        if ($tens === 8) {
            return $unit === 0 ? 'quatre-vingt'.($final ? 's' : '') : 'quatre-vingt-'.self::UNITS[$unit];
        }

        $base = self::TENS[$tens];

        return match ($unit) {
            0 => $base,
            1 => "{$base} et un",
            default => "{$base}-".self::UNITS[$unit],
        };
    }
}
