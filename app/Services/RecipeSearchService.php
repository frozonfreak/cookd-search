<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RecipeSearchService
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly VectorSearchService $vectorSearchService,
        private readonly PersonalizationService $personalizationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $dsl
     */
    public function search(array $dsl, int $limit = 50): Collection
    {
        $dsl = $this->normalizeDsl($dsl);
        $query = Recipe::query()->select('recipes.*');
        $hybridEnabled = $dsl['semantic']['query_text'] !== null || $dsl['personalization']['weight'] > 0;

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

        foreach ($dsl['nutrition'] as $metric => $constraint) {
            if (! $this->supportsNutritionMetric($metric)) {
                continue;
            }

            if (isset($constraint['min'])) {
                $query->whereNotNull($metric);
                $query->where($metric, '>=', (float) $constraint['min']);
            }

            if (isset($constraint['max'])) {
                $query->whereNotNull($metric);
                $query->where($metric, '<=', (float) $constraint['max']);
            }
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

        if ($this->supportsQuantityQueries()) {
            $this->applyQuantityConstraints($query, $dsl['quantity_constraints']);
        }

        $this->applyScoring($query, $dsl);

        $results = $query
            ->orderByDesc('total_score')
            ->orderBy('title')
            ->limit($hybridEnabled ? max($limit * 3, 60) : $limit)
            ->get();

        return $this->rerankResults($results, $dsl, $limit);
    }

    /**
     * @param  array<string, mixed>  $dsl
     */
    private function applyScoring(Builder $query, array $dsl): void
    {
        $supportsQuantityQueries = $this->supportsQuantityQueries();
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

        [$quantityScore, $quantityBindings] = $this->buildQuantityScore($dsl['quantity_constraints'], $supportsQuantityQueries);
        [$nutritionScore, $nutritionBindings] = $this->buildNutritionScore($dsl['nutrition']);
        [$tasteScore, $tasteBindings] = $this->buildTasteScore($dsl['taste_preferences']);

        $popularityScore = "LEAST(1, GREATEST(0, COALESCE(((raw_json->>'likes')::numeric - COALESCE((raw_json->>'dislikes')::numeric, 0)) / 100, 0)))";
        $recencyScore = "GREATEST(0, 1 - (EXTRACT(EPOCH FROM (NOW() - COALESCE(updated_at, created_at))) / 86400 / 365))";
        $weights = $dsl['scoring']['weights'] ?? [
            'w_all' => 2.0,
            'w_any' => 1.0,
            'w_quantity' => 0.5,
            'w_nutrition' => 0.6,
            'w_taste' => 0.8,
            'w_time' => 1.0,
            'w_pop' => 1.0,
            'w_rec' => 0.5,
            'exclude_penalty' => 1.0,
        ];

        $query
            ->selectRaw("{$allScore} as all_match_ratio", $allBindings)
            ->selectRaw("{$anyScore} as any_match_ratio", $anyBindings)
            ->selectRaw("{$quantityScore} as quantity_score", $quantityBindings)
            ->selectRaw("{$nutritionScore} as nutrition_score", $nutritionBindings)
            ->selectRaw("{$tasteScore} as taste_score", $tasteBindings)
            ->selectRaw("{$timeScore} as time_score", $timeBindings)
            ->selectRaw("{$popularityScore} as popularity_score")
            ->selectRaw("{$recencyScore} as recency_score");

        $scoreSql = '(('.$allScore.' * '.$weights['w_all'].') + ('.$anyScore.' * '.$weights['w_any'].') + ('.$quantityScore.' * '.$weights['w_quantity'].') + ('.$nutritionScore.' * '.$weights['w_nutrition'].') + ('.$tasteScore.' * '.$weights['w_taste'].') + ('.$timeScore.' * '.$weights['w_time'].') + ('.$popularityScore.' * '.$weights['w_pop'].') + ('.$recencyScore.' * '.$weights['w_rec'].')) * '.$weights['exclude_penalty'];
        $maxPossibleScore = max(
            0.0001,
            (float) $weights['w_all']
            + (float) $weights['w_any']
            + (float) $weights['w_quantity']
            + (float) $weights['w_nutrition']
            + (float) $weights['w_taste']
            + (float) $weights['w_time']
            + (float) $weights['w_pop']
            + (float) $weights['w_rec']
        );

        $scoreBindings = [...$allBindings, ...$anyBindings, ...$quantityBindings, ...$nutritionBindings, ...$tasteBindings, ...$timeBindings];

        $query->selectRaw($scoreSql.' as total_score', $scoreBindings);
        $query->selectRaw($allScore.' as ingredient_score', $allBindings);
        $query->selectRaw('('.$allScore.' * '.$weights['w_all'].') as w_all_score', $allBindings);
        $query->selectRaw('LEAST(100, GREATEST(0, ROUND((('.$scoreSql.') / '.$maxPossibleScore.') * 100, 0))) as display_score', [...$scoreBindings]);
    }

    /**
     * @param  array<string, mixed>  $dsl
     */
    private function rerankResults(Collection $results, array $dsl, int $limit): Collection
    {
        if ($results->isEmpty()) {
            return $results;
        }

        $semanticWeight = (float) ($dsl['semantic']['weight'] ?? 0.35);
        $personalizationWeight = (float) ($dsl['personalization']['weight'] ?? 0.0);
        $queryEmbedding = $dsl['semantic']['query_embedding'];
        $queryText = $dsl['semantic']['query_text'];
        $tasteEmbedding = $dsl['personalization']['taste_embedding'];
        $intentConfidence = (float) Arr::get($dsl, 'scoring.intent_confidence', 0.55);
        $structuredWeight = $intentConfidence >= 0.75 ? 0.6 : 0.45;
        $semanticWeight = $intentConfidence >= 0.75 ? min($semanticWeight, 0.4) : max($semanticWeight, 0.55);
        $semanticScores = $this->vectorSearchService->search(
            $queryEmbedding,
            $results->pluck('id')->map(fn ($id) => (int) $id)->all(),
            max($limit * 3, 40)
        );

        $reranked = $results->map(function (Recipe $recipe) use (
            $dsl,
            $semanticWeight,
            $personalizationWeight,
            $queryEmbedding,
            $queryText,
            $tasteEmbedding,
            $semanticScores,
            $structuredWeight,
        ): Recipe {
            $lexicalScore = (float) ($recipe->total_score ?? 0.0);
            $recipeEmbedding = $recipe->semantic_embedding;

            if (($recipeEmbedding === null || $recipeEmbedding === []) && $queryEmbedding !== []) {
                $recipeEmbedding = $this->embeddingService->embedText($this->buildRecipeDocument($recipe));
            }

            $semanticScore = (float) ($semanticScores[$recipe->id] ?? (
                $queryEmbedding !== []
                    ? $this->embeddingService->cosineSimilarity($queryEmbedding, $recipeEmbedding ?? [])
                    : 0.0
            ));

            $personalizationScore = $this->personalizationService->scoreRecipe($recipe, $dsl['personalization']);
            if ($personalizationScore === 0.0) {
                $personalizationScore = $this->calculatePersonalizationScore($recipe, $dsl['personalization'], $tasteEmbedding);
            }
            $exactBoost = $queryText !== null && Str::contains(
                Str::lower((string) $recipe->normalized_title),
                Str::lower($queryText)
            ) ? 0.15 : 0.0;

            $hybridScore = ($semanticScore * $semanticWeight)
                + ($lexicalScore * $structuredWeight)
                + ($personalizationScore * $personalizationWeight)
                + $exactBoost;

            $recipe->setAttribute('semantic_score', round($semanticScore, 4));
            $recipe->setAttribute('personalization_score', round($personalizationScore, 4));
            $recipe->setAttribute('hybrid_score', round($hybridScore, 4));
            $recipe->setAttribute('score_breakdown', [
                'ingredients' => round((float) ($recipe->ingredient_score ?? 0.0), 4),
                'quantity' => round((float) ($recipe->quantity_score ?? 0.0), 4),
                'nutrition' => round((float) ($recipe->nutrition_score ?? 0.0), 4),
                'taste' => round((float) ($recipe->taste_score ?? 0.0), 4),
                'time' => round((float) ($recipe->time_score ?? 0.0), 4),
                'lexical' => round($lexicalScore, 4),
                'semantic' => round($semanticScore, 4),
                'personalization' => round($personalizationScore, 4),
                'exact_boost' => $exactBoost,
                'intent' => Arr::get($dsl, 'scoring.intent', Arr::get($dsl, 'intent')),
            ]);
            $recipe->setAttribute('display_score', (int) min(
                100,
                max(0, round(((float) ($recipe->display_score ?? 0)) + ($semanticScore * 18) + ($personalizationScore * 14) + ($exactBoost * 100)))
            ));

            return $recipe;
        });

        /** @var Collection $sorted */
        $sorted = $reranked
            ->sortByDesc(fn (Recipe $recipe) => (float) ($recipe->hybrid_score ?? $recipe->total_score ?? 0.0))
            ->values();

        return $sorted->take($limit)->values();
    }

    /**
     * @param  array<string, mixed>  $dsl
     * @return array{
     *     include:array{all:array<int, string>, any:array<int, string>},
     *     exclude:array<int, string>,
     *     ingredient_relations:array<string, array<int, string>>,
     *     quantity_constraints:array<int, array{ingredient:string, quantity:array{min:?float, max:?float, target:?float}}>,
     *     nutrition:array<string, array{min?:float, max?:float}>,
     *     taste_preferences:array<string, float>,
     *     time:array{max:?int},
     *     dish_type:?string,
     *     meal_type:array<int, string>,
     *     inventory:array<int, string>,
     *     strict:bool,
     *     scoring?:array<string, mixed>,
     *     rewrite?:array<string, mixed>,
     *     semantic?:array<string, mixed>,
     *     personalization?:array<string, mixed>
     * }
     */
    private function normalizeDsl(array $dsl): array
    {
        $semantic = $dsl['semantic'] ?? [];
        $personalization = $dsl['personalization'] ?? [];

        $queryText = isset($semantic['query_text']) && is_string($semantic['query_text']) && trim($semantic['query_text']) !== ''
            ? trim($semantic['query_text'])
            : null;
        $queryEmbedding = array_values(array_map(
            'floatval',
            is_array($semantic['query_embedding'] ?? null) ? $semantic['query_embedding'] : []
        ));

        if ($queryText !== null && $queryEmbedding === []) {
            $queryEmbedding = $this->embeddingService->embedText($queryText);
        }

        $tasteEmbedding = array_values(array_map(
            'floatval',
            is_array($personalization['taste_embedding'] ?? null) ? $personalization['taste_embedding'] : []
        ));

        if ($tasteEmbedding === [] && array_filter([
            $personalization['preferred_ingredients'] ?? [],
            $personalization['preferred_dish_types'] ?? [],
            $personalization['preferred_cuisines'] ?? [],
        ]) !== []) {
            $tasteEmbedding = $this->embeddingService->embedText(implode(' ', [
                implode(' ', $personalization['preferred_ingredients'] ?? []),
                implode(' ', $personalization['preferred_dish_types'] ?? []),
                implode(' ', $personalization['preferred_cuisines'] ?? []),
            ]));
        }

        if (isset($dsl['include']['all'])) {
            return [
                'include' => [
                    'all' => array_values($dsl['include']['all']),
                    'any' => array_values($dsl['include']['any'] ?? []),
                ],
                'exclude' => array_values($dsl['exclude'] ?? []),
                'ingredient_relations' => $dsl['ingredient_relations'] ?? [
                    'exact' => [],
                    'strong_substitutes' => [],
                    'weak_substitutes' => [],
                ],
                'quantity_constraints' => array_values($dsl['quantity_constraints'] ?? []),
                'nutrition' => $dsl['nutrition'] ?? [],
                'taste_preferences' => $dsl['taste_preferences'] ?? [],
                'time' => [
                    'max' => $dsl['time']['max'] ?? null,
                ],
                'dish_type' => $dsl['dish_type'] ?? null,
                'meal_type' => array_values($dsl['meal_type'] ?? []),
                'inventory' => array_values($dsl['inventory'] ?? []),
                'strict' => (bool) ($dsl['strict'] ?? false),
                'scoring' => $dsl['scoring'] ?? [],
                'rewrite' => $dsl['rewrite'] ?? [],
                'semantic' => [
                    'query_text' => $queryText,
                    'query_embedding' => $queryEmbedding,
                    'weight' => (float) ($semantic['weight'] ?? 0.35),
                ],
                'personalization' => [
                    'user_id' => $personalization['user_id'] ?? null,
                    'preferred_ingredients' => array_values($personalization['preferred_ingredients'] ?? []),
                    'avoided_ingredients' => array_values($personalization['avoided_ingredients'] ?? []),
                    'preferred_dish_types' => array_values($personalization['preferred_dish_types'] ?? []),
                    'preferred_cuisines' => array_values($personalization['preferred_cuisines'] ?? []),
                    'taste_profile' => $personalization['taste_profile'] ?? $personalization['taste_preferences'] ?? [],
                    'taste_preferences' => $personalization['taste_preferences'] ?? [],
                    'taste_embedding' => $tasteEmbedding,
                    'weight' => (float) ($personalization['weight'] ?? 0.0),
                ],
            ];
        }

        return [
            'include' => [
                'all' => array_values($dsl['include'] ?? []),
                'any' => [],
            ],
            'exclude' => array_values($dsl['exclude'] ?? []),
            'ingredient_relations' => $dsl['ingredient_relations'] ?? [
                'exact' => [],
                'strong_substitutes' => [],
                'weak_substitutes' => [],
            ],
            'quantity_constraints' => array_values($dsl['quantity_constraints'] ?? []),
            'nutrition' => $dsl['nutrition'] ?? [],
            'taste_preferences' => $dsl['taste_preferences'] ?? [],
            'time' => [
                'max' => $dsl['max_cooking_time'] ?? null,
            ],
            'dish_type' => $dsl['dish_type'] ?? null,
            'meal_type' => isset($dsl['meal_type']) && $dsl['meal_type'] !== null ? [$dsl['meal_type']] : [],
            'inventory' => array_values($dsl['inventory'] ?? []),
            'strict' => (bool) ($dsl['strict'] ?? false),
            'scoring' => $dsl['scoring'] ?? [],
            'rewrite' => $dsl['rewrite'] ?? [],
            'semantic' => [
                'query_text' => $queryText,
                'query_embedding' => $queryEmbedding,
                'weight' => (float) ($semantic['weight'] ?? 0.35),
            ],
            'personalization' => [
                'user_id' => $personalization['user_id'] ?? null,
                'preferred_ingredients' => array_values($personalization['preferred_ingredients'] ?? []),
                'avoided_ingredients' => array_values($personalization['avoided_ingredients'] ?? []),
                'preferred_dish_types' => array_values($personalization['preferred_dish_types'] ?? []),
                'preferred_cuisines' => array_values($personalization['preferred_cuisines'] ?? []),
                'taste_profile' => $personalization['taste_profile'] ?? $personalization['taste_preferences'] ?? [],
                'taste_preferences' => $personalization['taste_preferences'] ?? [],
                'taste_embedding' => $tasteEmbedding,
                'weight' => (float) ($personalization['weight'] ?? 0.0),
            ],
        ];
    }

    private function buildRecipeDocument(Recipe $recipe): string
    {
        return Str::of(implode(' ', array_filter([
            $recipe->search_document,
            $recipe->title,
            $recipe->dish_type,
            is_array($recipe->meal_type) ? implode(' ', $recipe->meal_type) : null,
            is_array($recipe->cuisine) ? implode(' ', $recipe->cuisine) : null,
            is_array($recipe->ingredients) ? implode(' ', $recipe->ingredients) : null,
        ])))
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, float>  $tasteEmbedding
     */
    private function calculatePersonalizationScore(Recipe $recipe, array $profile, array $tasteEmbedding): float
    {
        $ingredients = array_map('strval', $recipe->ingredients ?? []);
        $preferredIngredients = array_map('strval', $profile['preferred_ingredients'] ?? []);
        $avoidedIngredients = array_map('strval', $profile['avoided_ingredients'] ?? []);
        $preferredDishTypes = array_map('strval', $profile['preferred_dish_types'] ?? []);
        $preferredCuisines = array_map('strval', $profile['preferred_cuisines'] ?? []);

        $ingredientAffinity = $preferredIngredients === []
            ? 0.0
            : count(array_intersect($ingredients, $preferredIngredients)) / max(count($preferredIngredients), 1);
        $avoidPenalty = count(array_intersect($ingredients, $avoidedIngredients)) > 0 ? 0.35 : 1.0;

        $dishTypeScore = 0.0;
        if ($preferredDishTypes !== [] && is_string($recipe->dish_type)) {
            foreach ($preferredDishTypes as $preferredDishType) {
                if (Str::contains(Str::lower($recipe->dish_type), Str::lower($preferredDishType))) {
                    $dishTypeScore = 1.0;
                    break;
                }
            }
        }

        $cuisineScore = 0.0;
        if ($preferredCuisines !== [] && is_array($recipe->cuisine)) {
            $cuisineScore = count(array_intersect(
                array_map('strval', $recipe->cuisine),
                $preferredCuisines
            )) / max(count($preferredCuisines), 1);
        }

        $tasteEmbeddingScore = $tasteEmbedding !== []
            ? $this->embeddingService->cosineSimilarity(
                $tasteEmbedding,
                $recipe->semantic_embedding ?? $this->embeddingService->embedText($this->buildRecipeDocument($recipe))
            )
            : 0.0;

        return round((($ingredientAffinity * 0.45) + ($dishTypeScore * 0.2) + ($cuisineScore * 0.15) + ($tasteEmbeddingScore * 0.2)) * $avoidPenalty, 4);
    }

    /**
     * @param  array<int, array{ingredient:string, quantity:array{min:?float, max:?float, target:?float}}>  $constraints
     */
    private function applyQuantityConstraints(Builder $query, array $constraints): void
    {
        foreach ($constraints as $constraint) {
            $ingredient = $constraint['ingredient'] ?? null;
            $quantity = $constraint['quantity'] ?? [];

            if (! is_string($ingredient) || trim($ingredient) === '') {
                continue;
            }

            $sql = "
                EXISTS (
                    SELECT 1
                    FROM recipe_ingredients ri
                    INNER JOIN ingredients i ON i.id = ri.ingredient_id
                    WHERE ri.recipe_id = recipes.id
                      AND i.name = ?
            ";
            $bindings = [$ingredient];

            if (array_key_exists('min', $quantity) && $quantity['min'] !== null) {
                $sql .= ' AND COALESCE(ri.quantity_value, 0) >= ?';
                $bindings[] = (float) $quantity['min'];
            }

            if (array_key_exists('max', $quantity) && $quantity['max'] !== null) {
                $sql .= ' AND COALESCE(ri.quantity_value, 0) <= ?';
                $bindings[] = (float) $quantity['max'];
            }

            $sql .= ')';

            $query->whereRaw($sql, $bindings);
        }
    }

    /**
     * @param  array<int, array{ingredient:string, quantity:array{min:?float, max:?float, target:?float}}>  $constraints
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function buildQuantityScore(array $constraints, bool $supportsQuantityQueries): array
    {
        if ($constraints === [] || ! $supportsQuantityQueries) {
            return ['0', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($constraints as $constraint) {
            $ingredient = $constraint['ingredient'] ?? null;
            $target = $constraint['quantity']['target'] ?? null;

            if (! is_string($ingredient) || $target === null) {
                continue;
            }

            $parts[] = "
                COALESCE((
                    SELECT GREATEST(0, 1 - ABS(COALESCE(ri.quantity_value, 0.5) - ?))
                    FROM recipe_ingredients ri
                    INNER JOIN ingredients i ON i.id = ri.ingredient_id
                    WHERE ri.recipe_id = recipes.id
                      AND i.name = ?
                    ORDER BY ri.quantity_value DESC NULLS LAST
                    LIMIT 1
                ), 0)
            ";
            $bindings[] = (float) $target;
            $bindings[] = $ingredient;
        }

        if ($parts === []) {
            return ['0', []];
        }

        return ['(('.implode(' + ', $parts).') / '.count($parts).')', $bindings];
    }

    /**
     * @param  array<string, array{min?:float, max?:float}>  $nutrition
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function buildNutritionScore(array $nutrition): array
    {
        if ($nutrition === []) {
            return ['0', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($nutrition as $metric => $constraint) {
            if (! $this->supportsNutritionMetric($metric)) {
                continue;
            }

            if (isset($constraint['max'])) {
                $parts[] = "CASE WHEN {$metric} IS NULL THEN 0 WHEN {$metric} <= ? THEN 1 ELSE GREATEST(0, 1 - (({$metric} - ?) / NULLIF(?, 0))) END";
                $bindings[] = (float) $constraint['max'];
                $bindings[] = (float) $constraint['max'];
                $bindings[] = max(1.0, (float) $constraint['max']);
            } elseif (isset($constraint['min'])) {
                $parts[] = "CASE WHEN {$metric} IS NULL THEN 0 WHEN {$metric} >= ? THEN 1 ELSE GREATEST(0, {$metric} / NULLIF(?, 0)) END";
                $bindings[] = (float) $constraint['min'];
                $bindings[] = max(1.0, (float) $constraint['min']);
            }
        }

        if ($parts === []) {
            return ['0', []];
        }

        return ['(('.implode(' + ', $parts).') / '.count($parts).')', $bindings];
    }

    /**
     * @param  array<string, float|int>  $preferences
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function buildTasteScore(array $preferences): array
    {
        if ($preferences === [] || ! $this->supportsTasteProfileQueries()) {
            return ['0', []];
        }

        $parts = [];
        $bindings = [];

        foreach ($preferences as $taste => $requested) {
            $parts[] = "GREATEST(0, 1 - ABS(COALESCE((taste_profile->>?)::numeric, 0) - ?))";
            $bindings[] = $taste;
            $bindings[] = (float) $requested;
        }

        return ['(('.implode(' + ', $parts).') / '.count($parts).')', $bindings];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    private function supportsQuantityQueries(): bool
    {
        return Schema::hasTable('recipe_ingredients')
            && Schema::hasTable('ingredients');
    }

    private function supportsNutritionMetric(string $metric): bool
    {
        return in_array($metric, ['calories', 'fat', 'protein', 'sodium'], true)
            && Schema::hasTable('recipes')
            && Schema::hasColumn('recipes', $metric);
    }

    private function supportsTasteProfileQueries(): bool
    {
        return Schema::hasTable('recipes')
            && Schema::hasColumn('recipes', 'taste_profile');
    }
}
