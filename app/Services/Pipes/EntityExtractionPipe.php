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
        $parsed = $this->queryParser->parse($context->rawQuery);

        $context->entities['ingredients']['include_all'] = $parsed['include'];
        $context->entities['ingredients']['exclude'] = $parsed['exclude'];
        $context->entities['ingredients']['raw_detected'] = array_values(array_unique([
            ...$parsed['include'],
            ...$parsed['exclude'],
            ...$parsed['inventory'],
        ]));
        $context->entities['time'] = [
            'max' => $parsed['max_cooking_time'],
            'raw' => $parsed['max_cooking_time'] !== null ? $parsed['max_cooking_time'].' min' : null,
        ];
        $context->entities['meal_type'] = $parsed['meal_type'] !== null ? [$parsed['meal_type']] : [];
        $context->entities['dish_type'] = $parsed['dish_type'];
        $context->scoring['modifiers']['strict_mode'] = $parsed['strict'];
        $context->scoring['modifiers']['boost_exact_match'] = $parsed['strict'];

        $context->log('entity_extraction', [
            'entities' => $context->entities,
        ]);

        return $next($context);
    }
}
