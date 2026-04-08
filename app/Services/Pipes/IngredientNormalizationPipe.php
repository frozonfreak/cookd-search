<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use App\Services\IngredientNormalizerService;
use Closure;

class IngredientNormalizationPipe
{
    public function __construct(
        private readonly IngredientNormalizerService $ingredientNormalizerService,
    ) {
    }

    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $includeAll = $context->entities['ingredients']['include_all'];
        $includeAny = $context->entities['ingredients']['include_any'];
        $exclude = $context->entities['ingredients']['exclude'];
        $rawDetected = $context->entities['ingredients']['raw_detected'];

        $normalizedIncludeAll = $this->ingredientNormalizerService->normalize($includeAll);
        $normalizedIncludeAny = $this->ingredientNormalizerService->normalize($includeAny);
        $normalizedExclude = $this->ingredientNormalizerService->normalize($exclude);

        $context->entities['ingredients']['include_all'] = $normalizedIncludeAll;
        $context->entities['ingredients']['include_any'] = $normalizedIncludeAny;
        $context->entities['ingredients']['exclude'] = $normalizedExclude;
        $context->normalized['ingredients'] = array_values(array_unique([
            ...$normalizedIncludeAll,
            ...$normalizedIncludeAny,
            ...$normalizedExclude,
        ]));
        $context->normalized['failed_matches'] = array_values(array_diff(
            $rawDetected,
            $context->normalized['ingredients']
        ));

        $context->log('ingredient_normalization', [
            'normalized' => $context->normalized,
            'entities' => $context->entities['ingredients'],
        ]);

        return $next($context);
    }
}
