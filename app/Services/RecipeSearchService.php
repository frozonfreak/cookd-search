<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RecipeSearchService
{
    /**
     * @param  array<string, mixed>  $dsl
     */
    public function search(array $dsl, int $limit = 50): Collection
    {
        $dsl = $this->normalizeDsl($dsl);
        $query = Recipe::query()->select('recipes.*');

        if ($dsl['dish_type'] !== null) {
            $dishType = $dsl['dish_type'];

            $query->where(function (Builder $builder) use ($dishType): void {
                $builder
                    ->where('dish_type', 'like', "%{$dishType}%")
                    ->orWhere('normalized_title', 'like', "%{$dishType}%");
            });
        }

        if ($dsl['meal_type'] !== []) {
            $query->whereRaw('meal_type @> ?::jsonb', [json_encode($dsl['meal_type'])]);
        }

        if ($dsl['time']['max'] !== null) {
            $query->whereNotNull('cooking_time');
            $query->where('cooking_time', '<=', $dsl['time']['max'] + 15);
        }

        if ($dsl['exclude'] !== []) {
            $query->whereRaw(
                'NOT (ingredients && ARRAY['.$this->placeholders($dsl['exclude']).']::text[])',
                $dsl['exclude']
            );
        }

        if ($dsl['include']['all'] !== []) {
            $query->whereRaw(
                'ingredients @> ARRAY['.$this->placeholders($dsl['include']['all']).']::text[]',
                $dsl['include']['all']
            );
        }

        if ($dsl['include']['any'] !== []) {
            $query->whereRaw(
                'ingredients && ARRAY['.$this->placeholders($dsl['include']['any']).']::text[]',
                $dsl['include']['any']
            );
        }

        if ($dsl['strict'] && $dsl['include']['all'] !== []) {
            $query->whereRaw(
                'ingredients <@ ARRAY['.$this->placeholders($dsl['include']['all']).']::text[]',
                $dsl['include']['all']
            );
        }

        $this->applyScoring($query, $dsl);

        return $query
            ->orderByDesc('total_score')
            ->orderBy('title')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $dsl
     */
    private function applyScoring(Builder $query, array $dsl): void
    {
        $allScore = '0';
        $allBindings = [];
        if ($dsl['include']['all'] !== []) {
            $allScore = sprintf(
                '(cardinality(array(SELECT unnest(ingredients) INTERSECT SELECT unnest(ARRAY[%s]::text[])))::numeric / %d)',
                $this->placeholders($dsl['include']['all']),
                count($dsl['include']['all'])
            );
            $allBindings = $dsl['include']['all'];
        }

        $anyTerms = array_values(array_unique([...$dsl['include']['any'], ...$dsl['inventory']]));
        $anyScore = '0';
        $anyBindings = [];
        if ($anyTerms !== []) {
            $anyScore = sprintf(
                '(cardinality(array(SELECT unnest(ingredients) INTERSECT SELECT unnest(ARRAY[%s]::text[])))::numeric / %d)',
                $this->placeholders($anyTerms),
                count($anyTerms)
            );
            $anyBindings = $anyTerms;
        }

        $timeScore = '0';
        $timeBindings = [];
        if ($dsl['time']['max'] !== null) {
            $timeScore = 'GREATEST(0, 1 - (GREATEST(cooking_time - ?, 0)::numeric / (? + 15)))';
            $timeBindings = [$dsl['time']['max'], $dsl['time']['max']];
        }

        $popularityScore = "LEAST(1, GREATEST(0, COALESCE(((raw_json->>'likes')::numeric - COALESCE((raw_json->>'dislikes')::numeric, 0)) / 100, 0)))";
        $recencyScore = "GREATEST(0, 1 - (EXTRACT(EPOCH FROM (NOW() - COALESCE(updated_at, created_at))) / 86400 / 365))";
        $weights = $dsl['scoring']['weights'] ?? [
            'w_all' => 2.0,
            'w_any' => 1.0,
            'w_time' => 1.0,
            'w_pop' => 1.0,
            'w_rec' => 0.5,
            'exclude_penalty' => 1.0,
        ];

        $query
            ->selectRaw("{$allScore} as all_match_ratio", $allBindings)
            ->selectRaw("{$anyScore} as any_match_ratio", $anyBindings)
            ->selectRaw("{$timeScore} as time_score", $timeBindings)
            ->selectRaw("{$popularityScore} as popularity_score")
            ->selectRaw("{$recencyScore} as recency_score");

        $scoreSql = '(('.$allScore.' * '.$weights['w_all'].') + ('.$anyScore.' * '.$weights['w_any'].') + ('.$timeScore.' * '.$weights['w_time'].') + ('.$popularityScore.' * '.$weights['w_pop'].') + ('.$recencyScore.' * '.$weights['w_rec'].')) * '.$weights['exclude_penalty'];

        $scoreBindings = [...$allBindings, ...$anyBindings, ...$timeBindings];

        $query->selectRaw($scoreSql.' as total_score', $scoreBindings);
        $query->selectRaw($allScore.' as ingredient_score', $allBindings);
    }

    /**
     * @param  array<string, mixed>  $dsl
     * @return array{
     *     include:array{all:array<int, string>, any:array<int, string>},
     *     exclude:array<int, string>,
     *     time:array{max:?int},
     *     dish_type:?string,
     *     meal_type:array<int, string>,
     *     inventory:array<int, string>,
     *     strict:bool,
     *     scoring?:array<string, mixed>
     * }
     */
    private function normalizeDsl(array $dsl): array
    {
        if (isset($dsl['include']['all'])) {
            return [
                'include' => [
                    'all' => array_values($dsl['include']['all']),
                    'any' => array_values($dsl['include']['any'] ?? []),
                ],
                'exclude' => array_values($dsl['exclude'] ?? []),
                'time' => [
                    'max' => $dsl['time']['max'] ?? null,
                ],
                'dish_type' => $dsl['dish_type'] ?? null,
                'meal_type' => array_values($dsl['meal_type'] ?? []),
                'inventory' => array_values($dsl['inventory'] ?? []),
                'strict' => (bool) ($dsl['strict'] ?? false),
                'scoring' => $dsl['scoring'] ?? [],
            ];
        }

        return [
            'include' => [
                'all' => array_values($dsl['include'] ?? []),
                'any' => [],
            ],
            'exclude' => array_values($dsl['exclude'] ?? []),
            'time' => [
                'max' => $dsl['max_cooking_time'] ?? null,
            ],
            'dish_type' => $dsl['dish_type'] ?? null,
            'meal_type' => isset($dsl['meal_type']) && $dsl['meal_type'] !== null ? [$dsl['meal_type']] : [],
            'inventory' => array_values($dsl['inventory'] ?? []),
            'strict' => (bool) ($dsl['strict'] ?? false),
            'scoring' => $dsl['scoring'] ?? [],
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
