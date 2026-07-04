<?php

namespace App\Support;

/**
 * Reduces a transaction description down to a merchant-ish key, shared by
 * the categorize workflow and recurring-charge detection.
 */
class TransactionNormalizer
{
    /**
     * Normalized key for grouping "similar" transactions together (e.g.
     * same payee, different reference numbers/dates).
     */
    public static function normalize(string $description): string
    {
        $key = self::label($description);
        $key = strtolower($key);
        $key = (string) preg_replace('/\d+/', '', $key);
        $key = (string) preg_replace('/[^a-z\s]/', '', $key);
        $key = (string) preg_replace('/\s+/', ' ', $key);

        return trim($key);
    }

    /**
     * Human-readable merchant label used both for display and as a
     * default name when suggesting a brand-new category.
     */
    public static function label(string $description): string
    {
        // cleanIngDescription() produces "Merchant – remittance info".
        if (str_contains($description, ' – ')) {
            $description = strstr($description, ' – ', true);
        }

        return trim($description !== false ? $description : '') ?: 'Unknown';
    }
}
