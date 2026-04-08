<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientAlias;
use App\Models\Recipe;
use Illuminate\Support\Str;

class IngredientCatalogSyncService
{
    private const DEFAULT_ALIASES = [
        'shallots' => 'onion',
        'small onion' => 'onion',
        'small onions' => 'onion',
        'curd' => 'yogurt',
        'hung curd' => 'yogurt',
        'dahi' => 'yogurt',
        'chilli' => 'green chilli',
        'chillies' => 'green chilli',
        'egg' => 'eggs',
    ];

    /**
     * @return array{ingredients:int, aliases:int}
     */
    public function sync(): array
    {
        $ingredientNames = Recipe::query()
            ->pluck('ingredients')
            ->flatten()
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn (string $value) => Str::of($value)->lower()->squish()->value())
            ->unique()
            ->values();

        $ingredients = [];

        foreach ($ingredientNames as $name) {
            $ingredient = Ingredient::query()->firstOrCreate(['name' => $name]);
            $ingredients[$name] = $ingredient;

            foreach ($this->aliasesFor($name) as $alias) {
                IngredientAlias::query()->firstOrCreate([
                    'alias' => $alias,
                ], [
                    'ingredient_id' => $ingredient->id,
                ]);
            }
        }

        foreach (self::DEFAULT_ALIASES as $alias => $canonical) {
            $ingredient = $ingredients[$canonical] ?? Ingredient::query()->firstOrCreate(['name' => $canonical]);
            $ingredients[$canonical] = $ingredient;

            IngredientAlias::query()->updateOrCreate(
                ['alias' => $alias],
                ['ingredient_id' => $ingredient->id]
            );
        }

        return [
            'ingredients' => Ingredient::query()->count(),
            'aliases' => IngredientAlias::query()->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function aliasesFor(string $name): array
    {
        $normalized = Str::of($name)->lower()->squish()->value();
        $simplified = Str::of($normalized)
            ->replaceMatches('/\([^)]*\)/', '')
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        $singular = Str::of($simplified)->singular()->value();

        return collect([$normalized, $simplified, $singular])
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
