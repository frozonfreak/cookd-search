<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientAlias;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class QueryRewriteService
{
    private const RESERVED_TERMS = [
        'with',
        'using',
        'without',
        'no',
        'only',
        'just',
        'and',
        'or',
        'but',
        'have',
        'i',
        'recipes',
        'recipe',
        'under',
        'within',
        'quick',
        'fast',
        'easy',
        'breakfast',
        'lunch',
        'dinner',
        'snack',
    ];

    private const SYNONYM_MAP = [
        'brekfast' => 'breakfast',
        'gravvy' => 'gravy',
        'currry' => 'curry',
        'tomoto' => 'tomato',
        'tamato' => 'tomato',
        'onoin' => 'onion',
        'potata' => 'potato',
        'veggie' => 'vegetable',
        'veggies' => 'vegetable',
    ];

    /**
     * @return array{
     *     query:string,
     *     corrections:array<int, array{from:string, to:string, reason:string}>,
     *     expanded_terms:array<int, string>
     * }
     */
    public function rewrite(string $query): array
    {
        $tokens = preg_split('/\s+/', trim($query)) ?: [];
        $vocabulary = $this->vocabulary();
        $corrections = [];
        $expandedTerms = [];

        foreach ($tokens as $index => $token) {
            if (! is_string($token) || $token === '' || is_numeric($token)) {
                continue;
            }

            if (isset(self::SYNONYM_MAP[$token])) {
                $expandedTerms[] = self::SYNONYM_MAP[$token];
                $corrections[] = [
                    'from' => $token,
                    'to' => self::SYNONYM_MAP[$token],
                    'reason' => 'domain synonym',
                ];
                $tokens[$index] = self::SYNONYM_MAP[$token];

                continue;
            }

            if (in_array($token, self::RESERVED_TERMS, true) || strlen($token) < 4 || in_array($token, $vocabulary, true)) {
                continue;
            }

            $candidate = $this->closestMatch($token, $vocabulary);

            if ($candidate === null) {
                continue;
            }

            $corrections[] = [
                'from' => $token,
                'to' => $candidate,
                'reason' => 'typo tolerance',
            ];
            $tokens[$index] = $candidate;
        }

        return [
            'query' => implode(' ', $tokens),
            'corrections' => $corrections,
            'expanded_terms' => array_values(array_unique($expandedTerms)),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function vocabulary(): array
    {
        $terms = self::RESERVED_TERMS;

        try {
            if (Schema::hasTable('ingredients')) {
                $terms = [
                    ...$terms,
                    ...Ingredient::query()->pluck('name')->all(),
                ];
            }

            if (Schema::hasTable('ingredient_aliases')) {
                $terms = [
                    ...$terms,
                    ...IngredientAlias::query()->pluck('alias')->all(),
                ];
            }
        } catch (Throwable) {
            // Fall back to the static domain vocabulary when the catalog is unavailable.
        }

        return array_values(array_unique(array_map(
            static fn (string $term): string => Str::lower(trim($term)),
            array_filter($terms, 'is_string')
        )));
    }

    /**
     * @param  array<int, string>  $vocabulary
     */
    private function closestMatch(string $token, array $vocabulary): ?string
    {
        $bestCandidate = null;
        $bestDistance = PHP_INT_MAX;
        $bestSimilarity = 0.0;

        foreach ($vocabulary as $candidate) {
            if ($candidate === '' || abs(strlen($candidate) - strlen($token)) > 3) {
                continue;
            }

            $distance = levenshtein($token, $candidate);

            if ($distance > 2) {
                continue;
            }

            similar_text($token, $candidate, $similarity);

            if ($distance < $bestDistance || ($distance === $bestDistance && $similarity > $bestSimilarity)) {
                $bestDistance = $distance;
                $bestSimilarity = $similarity;
                $bestCandidate = $candidate;
            }
        }

        return $bestSimilarity >= 70.0 ? $bestCandidate : null;
    }
}
