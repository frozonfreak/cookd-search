<?php

namespace App\Http\Controllers;

use App\Services\QueryPipeline;
use App\Services\RecipeSearchService;
use App\Services\UserTasteProfileService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeSearchController extends Controller
{
    public function __invoke(
        Request $request,
        QueryPipeline $queryPipeline,
        RecipeSearchService $recipeSearchService,
        UserTasteProfileService $userTasteProfileService,
    ): View
    {
        $query = trim((string) $request->query('q', ''));
        $viewMode = (string) $request->query('view', 'detailed');
        if (! in_array($viewMode, ['detailed', 'minimal'], true)) {
            $viewMode = 'detailed';
        }
        $parsed = null;
        $dsl = null;
        $context = null;
        $results = collect();
        $tasteProfile = $userTasteProfileService->resolve($request->user());

        if ($query !== '') {
            $context = $queryPipeline->process($query);
            $context->personalization = $tasteProfile;
            $parsed = [
                'dish_type' => $context->entities['dish_type'],
                'include' => $context->entities['ingredients']['include_all'],
                'include_any' => $context->entities['ingredients']['include_any'],
                'exclude' => $context->entities['ingredients']['exclude'],
                'quantity_constraints' => $context->entities['ingredients']['quantity_constraints'],
                'nutrition' => $context->entities['nutrition'],
                'taste_preferences' => $context->entities['taste_preferences'],
                'strict' => (bool) ($context->scoring['modifiers']['strict_mode'] ?? false),
                'meal_type' => $context->entities['meal_type'][0] ?? null,
                'inventory' => [],
                'max_cooking_time' => $context->entities['time']['max'],
            ];
            $dsl = array_merge($context->dsl ?? [], [
                'rewrite' => $context->rewrite,
                'semantic' => [
                    'query_text' => $context->rewrittenQuery ?? $context->cleanedQuery,
                    'query_embedding' => $context->semantic['query_embedding'],
                    'weight' => $context->semantic['weight'] ?? 0.35,
                ],
                'personalization' => $tasteProfile,
                'intent' => $context->intent,
            ]);
            $results = $recipeSearchService->search($dsl);
        }

        return view('welcome', [
            'query' => $query,
            'parsed' => $parsed,
            'dsl' => $dsl,
            'context' => $context,
            'results' => $results,
            'tasteProfile' => $tasteProfile,
            'viewMode' => $viewMode,
        ]);
    }
}
