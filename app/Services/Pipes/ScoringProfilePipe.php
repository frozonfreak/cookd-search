<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;

class ScoringProfilePipe
{
    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $profiles = [
            'quick_search' => [
                'w_all' => 1.2,
                'w_any' => 0.7,
                'w_quantity' => 0.5,
                'w_nutrition' => 0.4,
                'w_taste' => 0.5,
                'w_time' => 1.8,
                'w_pop' => 0.5,
                'w_rec' => 0.3,
                'exclude_penalty' => 1.0,
            ],
            'ingredient_strict' => [
                'w_all' => 2.3,
                'w_any' => 0.6,
                'w_quantity' => 1.0,
                'w_nutrition' => 0.3,
                'w_taste' => 0.4,
                'w_time' => 0.5,
                'w_pop' => 0.4,
                'w_rec' => 0.2,
                'exclude_penalty' => 0.0,
            ],
            'healthy' => [
                'w_all' => 1.0,
                'w_any' => 0.8,
                'w_quantity' => 0.5,
                'w_nutrition' => 1.8,
                'w_taste' => 0.5,
                'w_time' => 0.7,
                'w_pop' => 0.4,
                'w_rec' => 0.4,
                'exclude_penalty' => 1.0,
            ],
            'flavor_focused' => [
                'w_all' => 1.0,
                'w_any' => 1.0,
                'w_quantity' => 0.4,
                'w_nutrition' => 0.4,
                'w_taste' => 1.9,
                'w_time' => 0.5,
                'w_pop' => 0.7,
                'w_rec' => 0.4,
                'exclude_penalty' => 1.0,
            ],
            'exploratory' => [
                'w_all' => 1.0,
                'w_any' => 1.0,
                'w_quantity' => 0.4,
                'w_nutrition' => 0.5,
                'w_taste' => 0.7,
                'w_time' => 0.6,
                'w_pop' => 1.2,
                'w_rec' => 0.8,
                'exclude_penalty' => 1.0,
            ],
        ];

        $base = $profiles[$context->intent ?? 'exploratory'];
        $confidence = $context->intentConfidence;

        $weights = [];
        foreach ($base as $key => $value) {
            $weights[$key] = round($value * $confidence, 4);
        }

        if ($confidence < 0.6) {
            $weights['w_pop'] = round(($weights['w_pop'] ?? 1) + 0.4, 4);
            $context->scoring['modifiers']['strict_mode'] = false;
        }

        $context->scoring['intent'] = $context->intent;
        $context->scoring['intent_confidence'] = $confidence;

        $context->scoring['weights'] = $weights;
        $context->confidenceMap['scoring_profile'] = $confidence;

        $context->log('scoring_profile', [
            'weights' => $weights,
            'modifiers' => $context->scoring['modifiers'],
        ]);

        return $next($context);
    }
}
