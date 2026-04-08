<?php

namespace App\Services\Pipes;

use App\DTO\QueryContext;
use Closure;

class OperatorClassificationPipe
{
    private const OPERATORS = [
        'with' => 'include',
        'using' => 'include',
        'without' => 'exclude',
        'no' => 'exclude',
        'only' => 'strict',
        'just' => 'strict',
        'and' => 'and',
        'or' => 'or',
        'but' => 'boundary',
        'have' => 'inventory',
    ];

    public function handle(QueryContext $context, Closure $next): QueryContext
    {
        $context->classifiedTokens = array_map(function (string $token): array {
            return [
                'token' => $token,
                'type' => self::OPERATORS[$token] ?? 'term',
            ];
        }, $context->tokens);

        $context->log('operator_classification', [
            'classifiedTokens' => $context->classifiedTokens,
        ]);

        return $next($context);
    }
}
