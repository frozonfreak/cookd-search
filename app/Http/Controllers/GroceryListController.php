<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\GroceryListOptimizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroceryListController extends Controller
{
    public function generate(Request $request, GroceryListOptimizerService $optimizer): JsonResponse
    {
        $validated = $request->validate([
            'recipe_ids'   => 'required|array|min:1|max:20',
            'recipe_ids.*' => 'integer',
        ]);

        $recipes = Recipe::query()
            ->with('recipeIngredients')
            ->whereIn('id', $validated['recipe_ids'])
            ->get();

        $result = $optimizer->optimize(
            userId: $request->user()?->id ?? 0,
            recipes: $recipes,
            persist: false,
        );

        // Enrich items with readable ingredient names
        $ingredientIds = collect($result['items'])->pluck('ingredient_id')->filter()->unique()->all();

        $names = $ingredientIds
            ? Ingredient::query()->whereIn('id', $ingredientIds)->pluck('name', 'id')->all()
            : [];

        $result['items'] = array_map(static function (array $item) use ($names): array {
            $item['ingredient_name'] = $names[$item['ingredient_id']] ?? ('Ingredient #'.$item['ingredient_id']);
            return $item;
        }, $result['items']);

        return response()->json($result);
    }
}
