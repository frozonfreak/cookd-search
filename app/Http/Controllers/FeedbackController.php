<?php

namespace App\Http\Controllers;

use App\Services\RankingFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function store(Request $request, RankingFeedbackService $feedbackService): JsonResponse
    {
        $validated = $request->validate([
            'recipe_id' => 'required|integer',
            'action'    => 'required|in:view,click,save,cook,like,skip,dislike,search_click',
            'query_text' => 'nullable|string|max:500',
        ]);

        $feedback = $feedbackService->record(
            recipeId: (int) $validated['recipe_id'],
            action: $validated['action'],
            userId: $request->user()?->id,
            queryText: $validated['query_text'] ?? null,
        );

        return response()->json(['ok' => true, 'reward' => $feedback->reward_score]);
    }
}
