<?php

namespace App\Services;

use App\Models\GroceryList;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\UserPantryItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GroceryListOptimizerService
{
    public function __construct(
        private readonly IngredientAvailabilityService $ingredientAvailabilityService,
        private readonly SubstitutionService $substitutionService,
    ) {
    }

    /**
     * @param  Collection<int, Recipe>  $recipes
     * @return array{
     *     grocery_list_id:?int,
     *     estimated_total_cost:float,
     *     items:array<int, array<string, mixed>>
     * }
     */
    public function optimize(
        int $userId,
        Collection $recipes,
        ?int $mealPlanId = null,
        ?string $location = null,
        bool $persist = true,
    ): array {
        $requirements = $this->collectRequirements($recipes);
        $pantry = $this->pantryQuantities($userId);
        $items = [];
        $estimatedTotal = 0.0;

        foreach ($requirements as $ingredientId => $requirement) {
            $required = (float) $requirement['quantity'];
            $pantryUsed = min($required, (float) ($pantry[$ingredientId] ?? 0.0));
            $toBuy = max(0.0, $required - $pantryUsed);
            $availability = $this->ingredientAvailabilityService->check((int) $ingredientId, $location);
            $substitution = null;
            $notes = null;

            if ($toBuy > 0 && $availability['status'] === 'unavailable') {
                $ingredient = Ingredient::query()->find($ingredientId);
                $suggestions = $ingredient === null
                    ? []
                    : $this->substitutionService->suggest($ingredient, null, $location)['suggestions'];
                $substitution = $suggestions[0]['ingredient'] ?? null;
                $notes = $substitution === null ? 'Unavailable; no safe substitution found.' : 'Unavailable; substitution suggested.';
            }

            $estimatedCost = $availability['price_per_unit'] === null
                ? 0.0
                : round($toBuy * (float) $availability['price_per_unit'], 2);
            $estimatedTotal += $estimatedCost;

            $items[] = [
                'ingredient_id' => (int) $ingredientId,
                'required_quantity' => round($required, 4),
                'pantry_quantity_used' => round($pantryUsed, 4),
                'quantity_to_buy' => round($toBuy, 4),
                'unit' => $requirement['unit'],
                'estimated_cost' => $estimatedCost,
                'substitution_used' => $substitution,
                'notes' => $notes,
                'reuse_count' => $requirement['reuse_count'],
            ];
        }

        usort($items, fn (array $a, array $b) => $b['reuse_count'] <=> $a['reuse_count']);

        $groceryListId = null;
        if ($persist && Schema::hasTable('grocery_lists')) {
            $list = GroceryList::query()->create([
                'user_id' => $userId,
                'meal_plan_id' => $mealPlanId,
                'status' => 'draft',
                'estimated_total_cost' => round($estimatedTotal, 2),
            ]);
            $groceryListId = (int) $list->id;

            foreach ($items as $item) {
                $list->items()->create(collect($item)->except('reuse_count')->all());
            }
        }

        return [
            'grocery_list_id' => $groceryListId,
            'estimated_total_cost' => round($estimatedTotal, 2),
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, Recipe>  $recipes
     * @return array<int, array{quantity:float, unit:?string, reuse_count:int}>
     */
    private function collectRequirements(Collection $recipes): array
    {
        $requirements = [];

        foreach ($recipes as $recipe) {
            foreach ($recipe->recipeIngredients ?? [] as $ingredient) {
                $ingredientId = (int) $ingredient->ingredient_id;

                $requirements[$ingredientId] ??= [
                    'quantity' => 0.0,
                    'unit' => $ingredient->unit,
                    'reuse_count' => 0,
                ];
                $requirements[$ingredientId]['quantity'] += (float) ($ingredient->quantity_value ?? 1);
                $requirements[$ingredientId]['reuse_count']++;
            }
        }

        return $requirements;
    }

    /**
     * @return array<int, float>
     */
    private function pantryQuantities(int $userId): array
    {
        if (! Schema::hasTable('user_pantry_items')) {
            return [];
        }

        return UserPantryItem::query()
            ->where('user_id', $userId)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->selectRaw('ingredient_id, SUM(quantity_value) as quantity')
            ->groupBy('ingredient_id')
            ->pluck('quantity', 'ingredient_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }
}
