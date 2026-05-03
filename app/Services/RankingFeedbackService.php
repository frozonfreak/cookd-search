<?php

namespace App\Services;

use App\Models\RecipeRankingFeedback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RankingFeedbackService
{
    /**
     * @var array<string, float>
     */
    private array $rewards = [
        'view' => 0.1,
        'click' => 0.3,
        'search_click' => 0.3,
        'save' => 0.6,
        'cook' => 1.0,
        'like' => 1.0,
        'skip' => -0.3,
        'dislike' => -1.0,
    ];

    /**
     * @param  array<string, mixed>|null  $queryDsl
     */
    public function record(
        int $recipeId,
        string $action,
        ?int $userId = null,
        ?string $queryText = null,
        ?array $queryDsl = null,
    ): RecipeRankingFeedback {
        return RecipeRankingFeedback::query()->create([
            'user_id' => $userId,
            'recipe_id' => $recipeId,
            'query_text' => $queryText,
            'query_dsl' => $queryDsl,
            'action' => $action,
            'reward_score' => $this->rewardFor($action),
        ]);
    }

    /**
     * @param  array<int, int>  $recipeIds
     * @return array<int, float>
     */
    public function scoresFor(array $recipeIds, ?int $userId = null): array
    {
        $recipeIds = array_values(array_unique(array_map('intval', $recipeIds)));

        if ($recipeIds === [] || ! Schema::hasTable('recipe_ranking_feedback')) {
            return [];
        }

        $query = DB::table('recipe_ranking_feedback')
            ->selectRaw('recipe_id, SUM(reward_score * EXP(-0.04 * (EXTRACT(EPOCH FROM (NOW() - created_at)) / 86400))) as feedback_score')
            ->whereIn('recipe_id', $recipeIds)
            ->groupBy('recipe_id');

        if ($userId !== null) {
            $query->where(function ($builder) use ($userId): void {
                $builder->where('user_id', $userId)->orWhereNull('user_id');
            });
        }

        return $query
            ->pluck('feedback_score', 'recipe_id')
            ->map(fn ($score) => round((float) $score, 4))
            ->all();
    }

    public function rewardFor(string $action): float
    {
        return $this->rewards[$action] ?? 0.0;
    }
}
