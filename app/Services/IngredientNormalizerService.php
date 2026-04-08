<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientAlias;
use Illuminate\Support\Str;

class IngredientNormalizerService
{
    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    public function normalize(array $tokens): array
    {
        return collect($tokens)
            ->filter(fn ($token) => is_string($token) && trim($token) !== '')
            ->map(fn (string $token) => $this->normalizeToken($token))
            ->unique()
            ->values()
            ->all();
    }

    public function normalizeToken(string $token): string
    {
        $normalized = Str::of($token)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        $alias = IngredientAlias::query()
            ->with('ingredient:id,name')
            ->where('alias', $normalized)
            ->first();

        if ($alias !== null) {
            return $alias->ingredient->name;
        }

        $ingredient = Ingredient::query()
            ->where('name', $normalized)
            ->orWhere('name', 'like', $normalized.' %')
            ->first();

        if ($ingredient !== null) {
            return $ingredient->name;
        }

        return $normalized;
    }
}
