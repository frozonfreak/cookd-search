<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use App\Services\QueryRewriteService;
use Closure;

class QueryRewritePipe
{
    public function __construct(
        private readonly QueryRewriteService $queryRewriteService,
    ) {
    }

    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $rewritten = $this->queryRewriteService->rewrite($context->cleanedQuery);

        $context->rewrittenQuery = $rewritten['query'];
        $context->cleanedQuery = $rewritten['query'];
        $context->rewrite = [
            'corrections' => $rewritten['corrections'],
            'expanded_terms' => $rewritten['expanded_terms'],
        ];
        $context->semantic['query_text'] = $context->rewrittenQuery;

        $context->log('query_rewrite', $context->rewrite + [
            'rewritten_query' => $context->rewrittenQuery,
        ]);

        return $next($context);
    }
}
