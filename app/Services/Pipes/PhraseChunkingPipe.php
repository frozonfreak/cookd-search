<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;

class PhraseChunkingPipe
{
    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $phrases = preg_split('/\b(?:with|using|without|no|only|just|but|and|or)\b/', $context->cleanedQuery) ?: [];

        $context->phrases = array_values(array_filter(array_map('trim', $phrases)));

        $context->log('phrase_chunking', [
            'phrases' => $context->phrases,
        ]);

        return $next($context);
    }
}
