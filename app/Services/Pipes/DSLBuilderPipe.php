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
            'time' => [
                'max' => $context->entities['time']['max'],
            ],
            'dish_type' => $context->entities['dish_type'],
            'meal_type' => $context->entities['meal_type'],
            'dietary' => $context->entities['dietary'],
            'inventory' => [],
            'strict' => (bool) ($context->scoring['modifiers']['strict_mode'] ?? false),
            'scoring' => $context->scoring,
        ];

        $context->log('dsl_builder', [
            'dsl' => $context->dsl,
        ]);

        return $next($context);
    }
}
