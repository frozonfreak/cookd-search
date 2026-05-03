<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientRelation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SubstitutionService
{
    public function __construct(
        private readonly IngredientAvailabilityService $ingredientAvailabilityService,
    ) {
    }

    /**
     * @return array{
     *     missing:string,
     *     suggestions:array<int, array{
     *         ingredient:string,
     *         relation:string,
     *         confidence:float,
     *         taste_impact:string,
     *         quantity_adjustment:float
     *     }>
     * }
     */
    public function suggest(string|Ingredient $ingredient, ?string $dishType = null, ?string $location = null): array
    {
        $source = $ingredient instanceof Ingredient
            ? $ingredient
            : Ingredient::query()->where('name', $ingredient)->first();

        if ($source === null || ! Schema::hasTable('ingredient_relations')) {
            return [
                'missing' => $ingredient instanceof Ingredient ? $ingredient->name : $ingredient,
                'suggestions' => [],
            ];
        }

        $relations = IngredientRelation::query()
            ->with('relatedIngredient:id,name')
            ->where('ingredient_id', $source->id)
            ->whereIn('relation_type', ['substitute', 'weak_substitute'])
            ->orderByRaw("CASE relation_type WHEN 'substitute' THEN 0 ELSE 1 END")
            ->orderByDesc('strength')
            ->get();

        return [
            'missing' => $source->name,
            'suggestions' => $this->formatSuggestions($relations, $dishType, $location),
        ];
    }

    /**
     * @param  Collection<int, IngredientRelation>  $relations
     * @return array<int, array{
     *     ingredient:string,
     *     relation:string,
     *     confidence:float,
     *     taste_impact:string,
     *     quantity_adjustment:float
     * }>
     */
    private function formatSuggestions(Collection $relations, ?string $dishType, ?string $location): array
    {
        $suggestions = [];

        foreach ($relations as $relation) {
            $ingredient = $relation->relatedIngredient;

            if ($ingredient === null || ! $this->ingredientAvailabilityService->isAvailable($ingredient->id, $location)) {
                continue;
            }

            $isWeak = $relation->relation_type === 'weak_substitute';
            $confidence = max(0.05, min(1.0, (float) $relation->strength));

            $suggestions[] = [
                'ingredient' => $ingredient->name,
                'relation' => $relation->relation_type,
                'confidence' => round($isWeak ? $confidence * 0.65 : $confidence, 2),
                'taste_impact' => $this->tasteImpact($isWeak, $dishType),
                'quantity_adjustment' => $isWeak ? 0.75 : 0.9,
            ];
        }

        return $suggestions;
    }

    private function tasteImpact(bool $isWeak, ?string $dishType): string
    {
        if ($isWeak) {
            return $dishType === null
                ? 'noticeable change; taste before scaling'
                : "noticeable change for {$dishType}; taste before scaling";
        }

        return $dishType === null
            ? 'minor flavor shift'
            : "minor flavor shift for {$dishType}";
    }
}
