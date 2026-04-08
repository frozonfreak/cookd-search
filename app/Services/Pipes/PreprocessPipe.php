<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;
use Illuminate\Support\Str;

class PreprocessPipe
{
    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $context->cleanedQuery = Str::of($context->rawQuery)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();

        $context->log('preprocess', [
            'cleanedQuery' => $context->cleanedQuery,
        ]);

        return $next($context);
    }
}
