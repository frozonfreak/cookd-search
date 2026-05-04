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
