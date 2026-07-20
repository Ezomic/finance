<?php

namespace App\Support;

/**
 * Very simple keyword-based category guesser, shared by the CSV/XLS
 * importer and the manual categorization workflow.
 */
class CategoryGuesser
{
    /** @var array<string, list<string>> */
    private static array $rules = [
        'Groceries' => [
            'jumbo',
            'albert heijn',
            'lidl',
            'aldi',
            'kruidvat',
            'supermarkt',
            'ah ',
            'spar',
        ],
        'Transport' => [
            'ns ',
            'ovpay',
            'parkeer',
            'benzine',
            'shell',
            'bp ',
            'total energie',
            'bike',
            'ov-chipkaart',
        ],
        'Housing' => ['huur', 'rent', 'hypotheek', 'mortgage', 'woondi'],
        'Utilities' => [
            'vitens',
            'odido',
            'eneco',
            'vattenfall',
            'ziggo',
            'kpn',
            'tele2',
            't-mobile',
        ],
        'Entertainment' => [
            'netflix',
            'spotify',
            'disney',
            'youtube',
            'steam',
            'playstation',
        ],
        'Salary' => ['salaris', 'salary', 'loon', 'payroll'],
        'Insurance' => ['inshared', 'insurance', 'verzekering', 'allianz'],
    ];

    /**
     * Returns the guessed category name for a description, or null if no
     * keyword rule matches.
     */
    public static function guess(string $description): ?string
    {
        $desc = strtolower($description);

        foreach (self::$rules as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($desc, $keyword)) {
                    return $categoryName;
                }
            }
        }

        return null;
    }
}
