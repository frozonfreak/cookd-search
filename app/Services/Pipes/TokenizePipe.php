<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;

class TokenizePipe
{
    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $context->tokens = $context->cleanedQuery === ''
            ? []
            : (preg_split('/\s+/', $context->cleanedQuery) ?: []);
        $context->ngrams = $this->buildNgrams($context->tokens);

        $context->log('tokenize', [
            'tokens' => $context->tokens,
            'ngrams' => $context->ngrams,
        ]);

        return $next($context);
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function buildNgrams(array $tokens): array
    {
        $ngrams = [];
        $count = count($tokens);

        for ($size = 1; $size <= 3; $size++) {
            for ($i = 0; $i <= $count - $size; $i++) {
                $ngrams[] = implode(' ', array_slice($tokens, $i, $size));
            }
        }

        return array_values(array_unique($ngrams));
    }
}
