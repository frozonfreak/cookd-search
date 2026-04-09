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
        } elseif ($context->entities['nutrition'] !== [] || preg_match('/\b(?:healthy|low\s+(?:oil|fat|sodium)|high\s+protein)\b/', $query) === 1) {
            $context->intent = 'healthy';
            $context->intentConfidence = 0.84;
        } elseif ($context->entities['taste_preferences'] !== [] || preg_match('/\b(?:spicy|tangy|sweet|rich|savory)\b/', $query) === 1) {
            $context->intent = 'flavor_focused';
            $context->intentConfidence = 0.82;
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
