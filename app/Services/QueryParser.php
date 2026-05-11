<?php

namespace App\Services;

use Illuminate\Support\Str;

class QueryParser
{
    /**
     * @return array{
     *     dish_type:?string,
     *     include:array<int, string>,
     *     include_any:array<int, string>,
     *     exclude:array<int, string>,
     *     strict:bool,
     *     meal_type:?string,
     *     inventory:array<int, string>,
     *     max_cooking_time:?int,
     *     quantity_constraints:array<int, array{ingredient:string, quantity:array{min:?float, max:?float, target:?float}}>,
     *     nutrition:array<string, array{min?:float, max?:float}>,
     *     taste_preferences:array<string, float>
     * }
     */
    public function parse(string $query): array
    {
        $normalized = Str::of($query)->lower()->squish()->value();

        $strictIngredients = $this->extractIngredientSection($normalized, ['only', 'just']);
        $include = $this->extractIngredientSection($normalized, ['with', 'using']);

        // "with chicken or paneer" captures "chicken or paneer" as one segment.
        // Split on 'or' and route all parts to include_any (either is acceptable, not both required).
        $orPromoted = [];
        $trueInclude = [];
        foreach ($include as $item) {
            if (preg_match('/\bor\b/i', $item)) {
                foreach (preg_split('/\s+or\s+/i', $item) ?: [] as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $orPromoted[] = $part;
                    }
                }
            } else {
                $trueInclude[] = $item;
            }
        }
        $include = $trueInclude;

        $includeAny = array_merge($orPromoted, $this->extractIngredientSection($normalized, ['or']));
        $exclude = $this->extractIngredientSection($normalized, ['without', 'no']);
        $inventory = $this->extractIngredientSection($normalized, ['i have', 'have']);
        $quantityConstraints = $this->extractQuantityConstraints($normalized);

        $claimed = array_merge($include, $strictIngredients, $includeAny, $exclude, $inventory);
        $freeTokens = $this->extractFreeTokens($normalized, $claimed);

        return [
            'dish_type' => $this->detectKeyword($normalized, [
                'chutney',
                'curry',
                'gravy',
                'rice',
                'biryani',
                'breakfast',
                'salad',
                'soup',
                'fry',
                'masala',
            ], ['breakfast']),
            'include' => $this->uniqueIngredients([...$include, ...$strictIngredients]),
            'include_any' => $this->uniqueIngredients($includeAny),
            'exclude' => $exclude,
            'strict' => $strictIngredients !== [],
            'meal_type' => $this->detectKeyword($normalized, ['breakfast', 'lunch', 'dinner', 'snack']),
            'inventory' => $inventory,
            'max_cooking_time' => $this->detectMaxCookingTime($normalized),
            'quantity_constraints' => $quantityConstraints,
            'nutrition' => $this->extractNutritionConstraints($normalized),
            'taste_preferences' => $this->extractTastePreferences($normalized),
            'free_tokens' => $freeTokens,
        ];
    }

    /**
     * @param  array<int, string>  $phrases
     * @return array<int, string>
     */
    private function extractIngredientSection(string $query, array $phrases): array
    {
        foreach ($phrases as $phrase) {
            $pattern = '/\b'.preg_quote($phrase, '/').'\b\s+(.+?)(?=\b(?:but|with|using|without|no|only|just|i have|have|suggest|for|recipe|recipes|recipie|recipies|breakfast|lunch|dinner|snack|quick|fast|easy)\b|$)/i';

            if (preg_match($pattern, $query, $matches) === 1) {
                return $this->splitIngredients($matches[1]);
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function splitIngredients(string $segment): array
    {
        $parts = preg_split('/\s*(?:,|and|&)\s*/i', $segment) ?: [];

        return $this->uniqueIngredients(array_map(
            fn (string $value) => trim(Str::of($value)->replaceMatches('/\b(?:recipe|recipes|recipie|recipies|suggest|quick|fast|easy|but)\b/i', '')->value()),
            $parts
        ));
    }

    private function detectMaxCookingTime(string $query): ?int
    {
        if (preg_match('/\b(?:under|within|less than)\s+(\d+)\s*(?:min|mins|minute|minutes)\b/i', $query, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(\d+)\s*(?:min|mins|minute|minutes)\b/i', $query, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/\b(?:quick|fast|easy)\b/i', $query) === 1) {
            return 30;
        }

        return null;
    }

    /**
     * @return array<int, array{ingredient:string, quantity:array{min:?float, max:?float, target:?float}}>
     */
    private function extractQuantityConstraints(string $query): array
    {
        $patterns = [
            '/\bno\s+([a-z][a-z\s]+?)(?=\b(?:with|using|without|but|and|or|recipe|recipes|for|under|quick|fast|easy)\b|$)/i' => [
                'min' => 0.0,
                'max' => 0.0,
                'target' => 0.0,
            ],
            '/\blittle\s+([a-z][a-z\s]+?)(?=\b(?:with|using|without|but|and|or|recipe|recipes|for|under|quick|fast|easy)\b|$)/i' => [
                'min' => null,
                'max' => 0.3,
                'target' => 0.2,
            ],
            '/\bextra\s+([a-z][a-z\s]+?)(?=\b(?:with|using|without|but|and|or|recipe|recipes|for|under|quick|fast|easy)\b|$)/i' => [
                'min' => 0.7,
                'max' => null,
                'target' => 0.85,
            ],
        ];

        $constraints = [];

        foreach ($patterns as $pattern => $quantity) {
            if (preg_match_all($pattern, $query, $matches) !== false) {
                foreach ($matches[1] ?? [] as $ingredient) {
                    $normalizedIngredient = $this->uniqueIngredients([$ingredient])[0] ?? null;

                    if ($normalizedIngredient === null) {
                        continue;
                    }

                    $constraints[] = [
                        'ingredient' => $normalizedIngredient,
                        'quantity' => $quantity,
                    ];
                }
            }
        }

        return $constraints;
    }

    /**
     * @return array<string, array{min?:float, max?:float}>
     */
    private function extractNutritionConstraints(string $query): array
    {
        $constraints = [];

        if (preg_match('/\blow\s+(?:oil|fat)\b/i', $query) === 1) {
            $constraints['fat'] = ['max' => 10.0];
        }

        if (preg_match('/\blow\s+sodium\b/i', $query) === 1) {
            $constraints['sodium'] = ['max' => 400.0];
        }

        if (preg_match('/\bhigh\s+protein\b/i', $query) === 1) {
            $constraints['protein'] = ['min' => 20.0];
        }

        if (preg_match('/\blow\s+calorie|low\s+calories\b/i', $query) === 1) {
            $constraints['calories'] = ['max' => 500.0];
        }

        return $constraints;
    }

    /**
     * @return array<string, float>
     */
    private function extractTastePreferences(string $query): array
    {
        $map = [
            'spicy' => 0.9,
            'tangy' => 0.6,
            'sweet' => 0.6,
            'rich' => 0.7,
            'savory' => 0.65,
        ];

        $preferences = [];

        foreach ($map as $term => $weight) {
            if (preg_match('/\b'.preg_quote($term, '/').'\b/i', $query) === 1) {
                $preferences[$term] = $weight;
            }
        }

        return $preferences;
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array<int, string>  $excluded
     */
    private function detectKeyword(string $query, array $keywords, array $excluded = []): ?string
    {
        foreach ($keywords as $keyword) {
            if (in_array($keyword, $excluded, true)) {
                continue;
            }

            if (Str::contains($query, $keyword)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Extract bare noun tokens that weren't captured by keyword-based extraction.
     * These are likely ingredient hints (e.g. "egg" in "quick egg breakfast").
     *
     * @param  array<int, string>  $claimed  tokens already captured by structured extraction
     * @return array<int, string>
     */
    private function extractFreeTokens(string $query, array $claimed): array
    {
        // Remove time expressions first
        $stripped = (string) preg_replace('/\b(?:under|within|less than)\s+\d+\s*(?:min|mins|minute|minutes)\b/i', ' ', $query);
        $stripped = (string) preg_replace('/\b\d+\s*(?:min|mins|minute|minutes)\b/i', ' ', $stripped);

        // Words that are structural, not ingredients
        $noise = [
            'quick', 'fast', 'easy', 'ready', 'simple', 'tasty', 'healthy', 'light', 'crispy', 'warm', 'comforting',
            'breakfast', 'lunch', 'dinner', 'snack', 'meal', 'brunch',
            'recipe', 'recipes', 'dish', 'food', 'cuisine', 'style',
            'with', 'using', 'without', 'no', 'only', 'just',
            'and', 'or', 'but', 'for', 'in', 'a', 'an', 'the', 'to', 'of', 'at', 'as',
            'under', 'within', 'about', 'around', 'some', 'make',
            'i', 'me', 'my', 'have', 'want', 'need', 'give', 'suggest',
            'high', 'low', 'protein', 'calorie', 'calories', 'fat', 'oil', 'sodium',
            'spicy', 'tangy', 'sweet', 'rich', 'savory', 'creamy', 'mild', 'hot',
            'chutney', 'curry', 'gravy', 'rice', 'biryani', 'salad', 'soup', 'fry', 'masala',
        ];

        foreach ($noise as $word) {
            $stripped = (string) preg_replace('/\b'.preg_quote($word, '/').'\b/i', ' ', $stripped);
        }

        $stripped = (string) preg_replace('/\s{2,}/', ' ', trim($stripped));
        $tokens   = array_filter(
            preg_split('/\s+/', $stripped) ?: [],
            static fn (string $t) => strlen($t) >= 3 && ! is_numeric($t)
        );

        $claimedLower = array_map('strtolower', $claimed);

        return $this->uniqueIngredients(array_values(array_filter(
            $tokens,
            static fn (string $t) => ! in_array(strtolower($t), $claimedLower, true)
        )));
    }

    /**
     * @param  array<int, string>  $ingredients
     * @return array<int, string>
     */
    private function uniqueIngredients(array $ingredients): array
    {
        return collect($ingredients)
            ->filter(fn ($ingredient) => is_string($ingredient) && trim($ingredient) !== '')
            ->map(fn (string $ingredient) => Str::of($ingredient)->lower()->squish()->trim(',')->value())
            ->unique()
            ->values()
            ->all();
    }
}
