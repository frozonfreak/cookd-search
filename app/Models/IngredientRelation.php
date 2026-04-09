<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ingredient_id',
    'related_ingredient_id',
    'relation_type',
    'strength',
])]
class IngredientRelation extends Model
{
    public $timestamps = false;

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function relatedIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'related_ingredient_id');
    }
}
