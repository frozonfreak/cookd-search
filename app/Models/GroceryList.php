<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'meal_plan_id',
    'status',
    'estimated_total_cost',
])]
class GroceryList extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_total_cost' => 'float',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GroceryListItem::class);
    }
}
