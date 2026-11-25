<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Moroccan RIB: 24 digits (bank 3, city 3, account 16, key 2). The key makes
 * the whole number divisible by 97, which catches most typing mistakes.
 * Spaces and dashes are removed before this rule runs.
 */
class MoroccanRib implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{24}$/', $value)) {
            $fail('Le RIB doit contenir exactement 24 chiffres.');

            return;
        }

        if (self::mod97($value) !== 0) {
            $fail('Ce RIB n\'est pas valide (clé incorrecte) : vérifiez les chiffres.');
        }
    }

    /** Remainder of a long digit string divided by 97 (too big for an integer). */
    public static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
