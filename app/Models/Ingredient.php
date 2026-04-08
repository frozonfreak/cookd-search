<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Ingredient extends Model
{
    use HasFactory;

    public function aliases(): HasMany
    {
        return $this->hasMany(IngredientAlias::class);
    }
}
