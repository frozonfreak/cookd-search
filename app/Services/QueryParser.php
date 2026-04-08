<?php

namespace App\Services;

use Illuminate\Support\Str;

class QueryParser
{
    /**
     * @return array{
     *     dish_type:?string,
     *     include:array<int, string>,
     *     exclude:array<int, string>,
     *     strict:bool,
     *     meal_type:?string,
     *     inventory:array<int, string>,
     *     max_cooking_time:?int
     * }
     */
    public function parse(string $query): array
    {
        $normalized = Str::of($query)->lower()->squish()->value();

        $strictIngredients = $this->extractIngredientSection($normalized, ['only', 'just']);
        $include = $this->extractIngredientSection($normalized, ['with', 'using']);
        $exclude = $this->extractIngredientSection($normalized, ['without', 'no']);
        $inventory = $this->extractIngredientSection($normalized, ['i have', 'have']);

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
            'exclude' => $exclude,
            'strict' => $strictIngredients !== [],
            'meal_type' => $this->detectKeyword($normalized, ['breakfast', 'lunch', 'dinner', 'snack']),
            'inventory' => $inventory,
            'max_cooking_time' => $this->detectMaxCookingTime($normalized),
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
