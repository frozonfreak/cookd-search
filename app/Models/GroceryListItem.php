<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grocery_list_id',
    'ingredient_id',
    'required_quantity',
    'pantry_quantity_used',
    'quantity_to_buy',
    'unit',
    'estimated_cost',
    'substitution_used',
    'notes',
])]
class GroceryListItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_quantity' => 'float',
            'pantry_quantity_used' => 'float',
            'quantity_to_buy' => 'float',
            'estimated_cost' => 'float',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
