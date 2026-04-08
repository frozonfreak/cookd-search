<?php

namespace App\Http\Controllers;

use App\Services\QueryPipeline;
use App\Services\RecipeSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeSearchController extends Controller
{
    public function __invoke(
        Request $request,
        QueryPipeline $queryPipeline,
        RecipeSearchService $recipeSearchService
    ): View
    {
        $query = trim((string) $request->query('q', ''));
        $parsed = null;
        $dsl = null;
        $context = null;
        $results = collect();

        if ($query !== '') {
            $context = $queryPipeline->process($query);
            $parsed = [
                'dish_type' => $context->entities['dish_type'],
                'include' => $context->entities['ingredients']['include_all'],
                'exclude' => $context->entities['ingredients']['exclude'],
                'strict' => (bool) ($context->scoring['modifiers']['strict_mode'] ?? false),
                'meal_type' => $context->entities['meal_type'][0] ?? null,
                'inventory' => [],
                'max_cooking_time' => $context->entities['time']['max'],
            ];
            $dsl = $context->dsl;
            $results = $recipeSearchService->search($dsl);
        }

        return view('welcome', [
            'query' => $query,
            'parsed' => $parsed,
            'dsl' => $dsl,
            'context' => $context,
            'results' => $results,
        ]);
    }
}
