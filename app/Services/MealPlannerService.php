<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Collection;

class MealPlannerService
{
    /**
     * @param  array{
     *     days:int,
     *     meals_per_day:int,
     *     constraints?:array<string, mixed>,
     *     query?:string
     * }  $input
     * @return array<int, array{day:int, meals:array<string, int|null>}>
     */
    public function plan(array $input, Collection $candidateRecipes): array
    {
        $days = max(1, (int) ($input['days'] ?? 7));
        $mealSlots = array_slice(['breakfast', 'lunch', 'dinner', 'snack'], 0, max(1, (int) ($input['meals_per_day'] ?? 3)));
        $constraints = $input['constraints'] ?? [];

        $available = $candidateRecipes->values();
        $usedCounts = [];
        $ingredientCounts = [];
        $plans = [];

        for ($day = 1; $day <= $days; $day++) {
            $dayMeals = [];

            foreach ($mealSlots as $meal) {
                /** @var Recipe|null $choice */
                $choice = $available
                    ->sortByDesc(fn (Recipe $recipe) => $this->scoreMealCandidate($recipe, $constraints, $usedCounts, $ingredientCounts, $meal))
                    ->first();

                $dayMeals[$meal] = $choice?->id;

                if ($choice === null) {
                    continue;
                }

                $recipeKey = $this->recipeKey($choice);
                $usedCounts[$recipeKey] = ($usedCounts[$recipeKey] ?? 0) + 1;

                foreach ($choice->ingredients ?? [] as $ingredient) {
                    $ingredientCounts[$ingredient] = ($ingredientCounts[$ingredient] ?? 0) + 1;
                }

                $available = $available->reject(fn (Recipe $recipe) => $this->recipeKey($recipe) === $recipeKey)->values();

                if ($available->isEmpty()) {
                    $available = $candidateRecipes->values();
                }
            }

            $plans[] = [
                'day' => $day,
                'meals' => $dayMeals,
            ];
        }

        return $plans;
    }

    /**
     * @param  array<string, mixed>  $constraints
     * @param  array<int, int>  $usedCounts
     * @param  array<string, int>  $ingredientCounts
     */
    private function scoreMealCandidate(Recipe $recipe, array $constraints, array $usedCounts, array $ingredientCounts, string $meal): float
    {
        $nutritionMatch = $this->nutritionMatchScore($recipe, $constraints);
        $ingredientOverlapBonus = $this->ingredientReuseScore($recipe, $ingredientCounts);
        $repetitionPenalty = 1 + (($usedCounts[$this->recipeKey($recipe)] ?? 0) * 0.75);
        $mealAffinity = $this->mealAffinityScore($recipe, $meal);

        return round((($nutritionMatch + $ingredientOverlapBonus + $mealAffinity) / $repetitionPenalty), 4);
    }

    private function recipeKey(Recipe $recipe): string
    {
        $id = $recipe->getKey() ?? $recipe->getAttribute('id');

        return $id !== null ? (string) $id : spl_object_hash($recipe);
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private function nutritionMatchScore(Recipe $recipe, array $constraints): float
    {
        $score = 1.0;

        if (isset($constraints['diet']) && is_string($constraints['diet']) && $constraints['diet'] !== '') {
            $diet = strtolower($constraints['diet']);
            $score *= strtolower((string) ($recipe->dietary ?? '')) === $diet ? 1.2 : 0.6;
        }

        if (isset($constraints['protein_min']) && $recipe->protein !== null) {
            $score += min(1.0, ((float) $recipe->protein) / max(1.0, (float) $constraints['protein_min']));
        }

        if (isset($constraints['calories_per_day']) && $recipe->calories !== null) {
            $targetPerMeal = ((float) $constraints['calories_per_day']) / 3;
            $score += max(0.0, 1 - abs(((float) $recipe->calories) - $targetPerMeal) / max(1.0, $targetPerMeal));
        }

        return round($score, 4);
    }

    /**
     * @param  array<string, int>  $ingredientCounts
     */
    private function ingredientReuseScore(Recipe $recipe, array $ingredientCounts): float
    {
        $ingredients = array_map('strval', $recipe->ingredients ?? []);

        if ($ingredients === []) {
            return 0.0;
        }

        $overlap = 0;

        foreach ($ingredients as $ingredient) {
            $overlap += min(2, $ingredientCounts[$ingredient] ?? 0);
        }

        return round($overlap / max(1, count($ingredients)), 4);
    }

    private function mealAffinityScore(Recipe $recipe, string $meal): float
    {
        $mealTypes = array_map('strtolower', array_map('strval', $recipe->meal_type ?? []));

        if ($mealTypes === []) {
            return 0.0;
        }

        return in_array(strtolower($meal), $mealTypes, true) ? 0.8 : 0.1;
    }
}
