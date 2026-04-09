<?php

use App\Services\UserTasteProfileService;

it('returns an empty profile for guests', function () {
    $service = app(UserTasteProfileService::class);
    $profile = $service->resolve(null);

    expect($profile['weight'])->toBe(0.0)
        ->and($profile['taste_embedding'])->toBeArray()
        ->and($profile['preferred_ingredients'])->toBe([]);
});
