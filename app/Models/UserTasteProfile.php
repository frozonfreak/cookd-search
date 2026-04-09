<?php

namespace App\Models;

use App\Casts\PostgresFloatArrayCast;
use App\Casts\PostgresTextArrayCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'taste_profile',
    'preferred_ingredients',
    'avoided_ingredients',
    'preferred_dish_types',
    'preferred_cuisines',
    'taste_embedding',
    'weight',
])]
class UserTasteProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taste_profile' => 'array',
            'preferred_ingredients' => PostgresTextArrayCast::class,
            'avoided_ingredients' => PostgresTextArrayCast::class,
            'preferred_dish_types' => PostgresTextArrayCast::class,
            'preferred_cuisines' => PostgresTextArrayCast::class,
            'taste_embedding' => PostgresFloatArrayCast::class,
            'weight' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
