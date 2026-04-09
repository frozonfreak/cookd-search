<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use App\Services\IngredientResolutionService;
use Closure;

class IngredientResolutionPipe
{
    public function __construct(
        private readonly IngredientResolutionService $ingredientResolutionService,
    ) {
    }

    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $resolved = $this->ingredientResolutionService->resolve(
            $context->entities['ingredients']['include_all'],
            $context->entities['ingredients']['include_any'],
            $context->entities['ingredients']['exclude'],
        );

        $context->entities['ingredients']['include_all'] = $resolved['include_all'];
        $context->entities['ingredients']['include_any'] = $resolved['include_any'];
        $context->entities['ingredients']['exclude'] = $resolved['exclude'];
        $context->entities['ingredient_relations'] = [
            'exact' => $resolved['exact'],
            'strong_substitutes' => $resolved['strong_substitutes'],
            'weak_substitutes' => $resolved['weak_substitutes'],
        ];
        $context->normalized['ingredients'] = array_values(array_unique([
            ...$resolved['include_all'],
            ...$resolved['include_any'],
            ...$resolved['exclude'],
        ]));
        $context->normalized['failed_matches'] = array_values(array_diff(
            $context->entities['ingredients']['raw_detected'],
            $context->normalized['ingredients']
        ));
        $context->normalized['ingredient_relations'] = $context->entities['ingredient_relations'];

        $context->log('ingredient_resolution', [
            'ingredients' => $context->entities['ingredients'],
            'relations' => $context->entities['ingredient_relations'],
        ]);

        return $next($context);
    }
}
