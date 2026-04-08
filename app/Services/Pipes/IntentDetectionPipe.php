<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;
use Illuminate\Support\Str;

class IntentDetectionPipe
{
    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $query = $context->cleanedQuery;

        if (preg_match('/\b(?:quick|fast|easy|under\s+\d+\s*(?:min|mins|minute|minutes))\b/', $query) === 1) {
            $context->intent = 'quick_search';
            $context->intentConfidence = 0.9;
        } elseif (
            $context->entities['ingredients']['include_all'] !== []
            && $context->entities['ingredients']['exclude'] !== []
            && Str::contains($query, 'but')
        ) {
            $context->intent = 'ingredient_strict';
            $context->intentConfidence = 0.88;
        } else {
            $context->intent = 'exploratory';
            $context->intentConfidence = 0.55;
        }

        $context->confidenceMap['intent'] = $context->intentConfidence;

        $context->log('intent_detection', [
            'intent' => $context->intent,
            'intentConfidence' => $context->intentConfidence,
        ]);

        return $next($context);
    }
}
