<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use App\Services\EmbeddingService;
use Closure;

class EmbeddingQueryPipe
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {
    }

    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $positiveTerms = array_values(array_filter([
            $context->entities['dish_type'] ?? null,
            ...($context->entities['ingredients']['include_all'] ?? []),
            ...($context->entities['ingredients']['include_any'] ?? []),
            ...($context->entities['meal_type'] ?? []),
        ]));
        $text = $positiveTerms !== []
            ? implode(' ', $positiveTerms)
            : ($context->rewrittenQuery ?? $context->cleanedQuery);

        if ($text !== '') {
            $context->semantic['query_text'] = $text;
            $context->semantic['query_embedding'] = $this->embeddingService->embedText($text);
        }

        $context->log('embedding_query', [
            'query_text' => $context->semantic['query_text'],
            'embedding_dimensions' => count($context->semantic['query_embedding']),
        ]);

        return $next($context);
    }
}
