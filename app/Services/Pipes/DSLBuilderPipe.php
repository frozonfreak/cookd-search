<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;

class DSLBuilderPipe
{
    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $context->dsl = [
            'include' => [
                'all' => $context->entities['ingredients']['include_all'],
                'any' => $context->entities['ingredients']['include_any'],
            ],
            'exclude' => $context->entities['ingredients']['exclude'],
            'ingredient_relations' => $context->entities['ingredient_relations'],
            'quantity_constraints' => $context->entities['ingredients']['quantity_constraints'],
            'nutrition' => $context->entities['nutrition'],
            'taste_preferences' => $context->entities['taste_preferences'],
            'time' => [
                'max' => $context->entities['time']['max'],
            ],
            'dish_type' => $context->entities['dish_type'],
            'meal_type' => $context->entities['meal_type'],
            'dietary' => $context->entities['dietary'],
            'inventory' => [],
            'strict' => (bool) ($context->scoring['modifiers']['strict_mode'] ?? false),
            'scoring' => $context->scoring,
            'rewrite' => $context->rewrite,
            'semantic' => [
                'query_text' => $context->rewrittenQuery ?? $context->cleanedQuery,
                'query_embedding' => $context->semantic['query_embedding'],
                'weight' => $context->semantic['weight'] ?? 0.35,
            ],
            'personalization' => $context->personalization,
        ];

        $context->log('dsl_builder', [
            'dsl' => $context->dsl,
        ]);

        return $next($context);
    }
}
