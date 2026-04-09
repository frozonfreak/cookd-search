<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use App\Services\QueryParser;
use Closure;

class EntityExtractionPipe
{
    public function __construct(
        private readonly QueryParser $queryParser,
    ) {
    }

    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $parsed = $this->queryParser->parse($context->rewrittenQuery ?? $context->cleanedQuery ?: $context->rawQuery);

        $context->entities['ingredients']['include_all'] = $parsed['include'];
        $context->entities['ingredients']['include_any'] = $parsed['include_any'];
        $context->entities['ingredients']['exclude'] = $parsed['exclude'];
        $context->entities['ingredients']['quantity_constraints'] = $parsed['quantity_constraints'];
        $context->entities['ingredients']['raw_detected'] = array_values(array_unique([
            ...$parsed['include'],
            ...$parsed['include_any'],
            ...$parsed['exclude'],
            ...$parsed['inventory'],
            ...array_map(
                static fn (array $constraint): string => (string) ($constraint['ingredient'] ?? ''),
                $parsed['quantity_constraints']
            ),
        ]));
        $context->entities['time'] = [
            'max' => $parsed['max_cooking_time'],
            'raw' => $parsed['max_cooking_time'] !== null ? $parsed['max_cooking_time'].' min' : null,
        ];
        $context->entities['meal_type'] = $parsed['meal_type'] !== null ? [$parsed['meal_type']] : [];
        $context->entities['dish_type'] = $parsed['dish_type'];
        $context->entities['nutrition'] = $parsed['nutrition'];
        $context->entities['taste_preferences'] = $parsed['taste_preferences'];
        $context->scoring['modifiers']['strict_mode'] = $parsed['strict'];
        $context->scoring['modifiers']['boost_exact_match'] = $parsed['strict'];

        $context->log('entity_extraction', [
            'entities' => $context->entities,
        ]);

        return $next($context);
    }
}
