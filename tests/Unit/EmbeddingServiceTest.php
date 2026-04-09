<?php

use App\Services\EmbeddingService;

it('creates deterministic normalized embeddings', function () {
    $service = new EmbeddingService;

    $left = $service->embedText('tomato onion curry');
    $right = $service->embedText('tomato onion curry');

    expect($left)->toHaveCount(1536)
        ->and($left)->toBe($right)
        ->and($service->cosineSimilarity($left, $right))->toBe(1.0);
});
