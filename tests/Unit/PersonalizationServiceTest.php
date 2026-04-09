<?php

use App\Models\Recipe;
use App\Services\PersonalizationService;

it('scores recipes against a user taste profile', function () {
    $service = app(PersonalizationService::class);
    $recipe = new Recipe([
        'taste_profile' => [
            'spicy' => 0.8,
            'sweet' => 0.2,
        ],
    ]);

    $score = $service->scoreRecipe($recipe, [
        'taste_profile' => [
            'spicy' => 0.9,
            'sweet' => 0.1,
        ],
    ]);

    expect($score)->toBeGreaterThan(0.85);
});
