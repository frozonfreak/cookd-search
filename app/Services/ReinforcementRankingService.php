<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ReinforcementRankingService
{
    public function __construct(
        private readonly RankingFeedbackService $rankingFeedbackService,
    ) {
    }

    /**
     * @param  EloquentCollection<int, Recipe>  $recipes
     * @return EloquentCollection<int, Recipe>
     */
    public function rerank(EloquentCollection $recipes, ?int $userId = null, float $feedbackWeight = 0.15): EloquentCollection
    {
        if ($recipes->isEmpty()) {
            return $recipes;
        }

        $feedbackScores = $this->rankingFeedbackService->scoresFor(
            $recipes->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $userId
        );

        $ranked = $recipes->map(function (Recipe $recipe) use ($feedbackScores, $feedbackWeight): Recipe {
            $baseScore = (float) ($recipe->hybrid_score ?? $recipe->total_score ?? 0.0);
            $feedbackScore = (float) ($feedbackScores[$recipe->id] ?? 0.0);
            $finalScore = $baseScore + ($feedbackScore * $feedbackWeight);

            $breakdown = $recipe->getAttribute('score_breakdown') ?? [];
            $breakdown['feedback'] = round($feedbackScore, 4);

            $recipe->setAttribute('feedback_score', round($feedbackScore, 4));
            $recipe->setAttribute('final_score', round($finalScore, 4));
            $recipe->setAttribute('score_breakdown', $breakdown);

            return $recipe;
        });

        /** @var EloquentCollection<int, Recipe> $sorted */
        $sorted = $ranked
            ->sortByDesc(fn (Recipe $recipe) => (float) ($recipe->final_score ?? $recipe->hybrid_score ?? 0.0))
            ->values();

        return $sorted;
    }
}
