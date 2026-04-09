<?php

namespace App\Models;

use App\Casts\PgvectorCast;
use App\Casts\PostgresIntegerArrayCast;
use App\Casts\PostgresFloatArrayCast;
use App\Casts\PostgresTextArrayCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'normalized_title',
    'ingredients',
    'ingredient_ids',
    'dish_type',
    'meal_type',
    'cuisine',
    'cooking_time',
    'dietary',
    'calories',
    'fat',
    'protein',
    'sodium',
    'nutrition',
    'taste_profile',
    'search_document',
    'embedding',
    'semantic_embedding',
    'raw_json',
])]
class Recipe extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ingredients' => PostgresTextArrayCast::class,
            'ingredient_ids' => PostgresIntegerArrayCast::class,
            'meal_type' => 'array',
            'cuisine' => 'array',
            'nutrition' => 'array',
            'taste_profile' => 'array',
            'calories' => 'float',
            'fat' => 'float',
            'protein' => 'float',
            'sodium' => 'float',
            'embedding' => PgvectorCast::class,
            'semantic_embedding' => PostgresFloatArrayCast::class,
            'raw_json' => 'array',
        ];
    }
}
