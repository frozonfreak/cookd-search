<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientRelation;
use Illuminate\Support\Facades\Schema;

class IngredientResolutionService
{
    public function __construct(
        private readonly IngredientNormalizerService $ingredientNormalizerService,
    ) {
    }

    /**
     * @param  array<int, string>  $includeAll
     * @param  array<int, string>  $includeAny
     * @param  array<int, string>  $exclude
     * @return array{
     *     include_all:array<int, string>,
     *     include_any:array<int, string>,
     *     exclude:array<int, string>,
     *     exact:array<int, string>,
     *     strong_substitutes:array<int, string>,
     *     weak_substitutes:array<int, string>
     * }
     */
    public function resolve(array $includeAll, array $includeAny, array $exclude): array
    {
        $normalizedIncludeAll = $this->ingredientNormalizerService->normalize($includeAll);
        $normalizedIncludeAny = $this->ingredientNormalizerService->normalize($includeAny);
        $normalizedExclude = $this->ingredientNormalizerService->normalize($exclude);

        [$strongInclude, $weakInclude] = $this->relatedIngredients($normalizedIncludeAll, true);
        [$strongExclude] = $this->relatedIngredients($normalizedExclude, false);

        return [
            'include_all' => $normalizedIncludeAll,
            'include_any' => array_values(array_unique([...$normalizedIncludeAny, ...$strongInclude, ...$weakInclude])),
            'exclude' => array_values(array_unique([...$normalizedExclude, ...$strongExclude])),
            'exact' => array_values(array_unique([...$normalizedIncludeAll, ...$normalizedIncludeAny, ...$normalizedExclude])),
            'strong_substitutes' => $strongInclude,
            'weak_substitutes' => $weakInclude,
        ];
    }

    /**
     * @param  array<int, string>  $ingredients
     * @return array{0:array<int, string>, 1:array<int, string>}
     */
    private function relatedIngredients(array $ingredients, bool $allowWeak): array
    {
        if ($ingredients === [] || ! Schema::hasTable('ingredient_relations')) {
            return [[], []];
        }

        $ingredientIds = Ingredient::query()
            ->whereIn('name', $ingredients)
            ->pluck('id')
            ->all();

        if ($ingredientIds === []) {
            return [[], []];
        }

        $rows = IngredientRelation::query()
            ->with('relatedIngredient:id,name')
            ->whereIn('ingredient_id', $ingredientIds)
            ->whereIn('relation_type', $allowWeak ? ['similar', 'substitute', 'weak_substitute'] : ['similar', 'substitute'])
            ->get();

        $strong = [];
        $weak = [];

        foreach ($rows as $row) {
            $name = $row->relatedIngredient?->name;

            if ($name === null) {
                continue;
            }

            if ($row->relation_type === 'weak_substitute') {
                $weak[] = $name;
            } else {
                $strong[] = $name;
            }
        }

        return [
            array_values(array_unique($strong)),
            array_values(array_unique($weak)),
        ];
    }
}
