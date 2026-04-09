<?php

use App\Services\QueryParser;

it('extracts quantity nutrition and taste constraints from the query', function () {
    $parser = new QueryParser;

    $parsed = $parser->parse('spicy tangy curry with extra onion and little garlic low sodium high protein');

    expect($parsed['quantity_constraints'])->toHaveCount(2)
        ->and($parsed['nutrition']['sodium']['max'])->toBe(400.0)
        ->and($parsed['nutrition']['protein']['min'])->toBe(20.0)
        ->and($parsed['taste_preferences']['spicy'])->toBe(0.9)
        ->and($parsed['taste_preferences']['tangy'])->toBe(0.6);
});
