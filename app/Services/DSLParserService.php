<?php

namespace App\Services;

class DSLParserService
{
    public function __construct(
        private readonly QueryParser $queryParser,
        private readonly IngredientNormalizerService $ingredientNormalizerService,
    ) {
    }

    /**
     * @return array{
     *     include:array{all:array<int, string>, any:array<int, string>},
     *     exclude:array<int, string>,
     *     time:array{max:?int},
     *     dish_type:?string,
     *     meal_type:array<int, string>,
     *     inventory:array<int, string>,
     *     strict:bool
     * }
     */
    public function parse(string $query): array
    {
        return $this->fromParsed($this->queryParser->parse($query));
    }

    /**
     * @param  array{
     *     dish_type:?string,
     *     include:array<int, string>,
     *     exclude:array<int, string>,
     *     strict:bool,
     *     meal_type:?string,
     *     inventory:array<int, string>,
     *     max_cooking_time:?int
     * }  $parsed
     * @return array{
     *     include:array{all:array<int, string>, any:array<int, string>},
     *     exclude:array<int, string>,
     *     time:array{max:?int},
     *     dish_type:?string,
     *     meal_type:array<int, string>,
     *     inventory:array<int, string>,
     *     strict:bool
     * }
     */
    public function fromParsed(array $parsed): array
    {
        return [
            'include' => [
                'all' => $this->ingredientNormalizerService->normalize($parsed['include']),
                'any' => [],
            ],
            'exclude' => $this->ingredientNormalizerService->normalize($parsed['exclude']),
            'time' => [
                'max' => $parsed['max_cooking_time'],
            ],
            'dish_type' => $parsed['dish_type'],
            'meal_type' => $parsed['meal_type'] !== null ? [$parsed['meal_type']] : [],
            'inventory' => $this->ingredientNormalizerService->normalize($parsed['inventory']),
            'strict' => $parsed['strict'],
        ];
    }
}
