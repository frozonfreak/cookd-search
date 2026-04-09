<?php

use App\Services\QueryRewriteService;

it('rewrites obvious typos before parsing', function () {
    $service = new QueryRewriteService;

    $result = $service->rewrite('onoin gravvy brekfast');

    expect($result['query'])->toBe('onion gravy breakfast')
        ->and($result['corrections'])->toHaveCount(3);
});
