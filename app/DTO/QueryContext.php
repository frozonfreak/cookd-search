<?php

namespace App\DTO;

class QueryContext
{
    /**
     * @param  array<int, string>  $tokens
     * @param  array<int, array<string, mixed>>  $classifiedTokens
     * @param  array<int, string>  $phrases
     * @param  array<int, string>  $ngrams
     * @param  array<string, mixed>  $entities
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $scoring
     * @param  array<string, float|int|string|bool|array<mixed>>  $confidenceMap
     * @param  array<string, mixed>|null  $dsl
     * @param  array<int, array<string, mixed>>  $debug
     */
    public function __construct(
        public string $rawQuery,
        public string $cleanedQuery = '',
        public array $tokens = [],
        public array $classifiedTokens = [],
        public array $phrases = [],
        public array $ngrams = [],
        public array $entities = [
            'ingredients' => [
                'include_all' => [],
                'include_any' => [],
                'exclude' => [],
                'raw_detected' => [],
            ],
            'time' => [
                'max' => null,
                'raw' => null,
            ],
            'meal_type' => [],
            'dish_type' => null,
            'dietary' => null,
        ],
        public array $normalized = [
            'ingredients' => [],
            'failed_matches' => [],
        ],
        public ?string $intent = null,
        public float $intentConfidence = 0.0,
        public array $scoring = [
            'weights' => [],
            'modifiers' => [
                'strict_mode' => false,
                'boost_exact_match' => false,
            ],
        ],
        public array $confidenceMap = [],
        public ?array $dsl = null,
        public array $debug = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(string $stage, array $payload): void
    {
        $this->debug[] = [
            'stage' => $stage,
            'payload' => $payload,
        ];
    }
}
