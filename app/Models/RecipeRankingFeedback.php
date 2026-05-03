<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'recipe_id',
    'query_text',
    'query_dsl',
    'action',
    'reward_score',
])]
class RecipeRankingFeedback extends Model
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query_dsl' => 'array',
            'reward_score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
