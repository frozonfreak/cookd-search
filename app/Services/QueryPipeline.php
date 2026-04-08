<?php

namespace App\Services;

use App\DTO\QueryContext;
use App\Services\Pipes\DSLBuilderPipe;
use App\Services\Pipes\EntityExtractionPipe;
use App\Services\Pipes\IngredientNormalizationPipe;
use App\Services\Pipes\IntentDetectionPipe;
use App\Services\Pipes\OperatorClassificationPipe;
use App\Services\Pipes\PhraseChunkingPipe;
use App\Services\Pipes\PreprocessPipe;
use App\Services\Pipes\ScoringProfilePipe;
use App\Services\Pipes\TokenizePipe;
use Illuminate\Pipeline\Pipeline;

class QueryPipeline
{
    public function __construct(
        private readonly Pipeline $pipeline,
    ) {
    }

    public function process(string $query): QueryContext
    {
        $context = new QueryContext(rawQuery: $query);

        /** @var QueryContext $result */
        $result = $this->pipeline
            ->send($context)
            ->through([
                PreprocessPipe::class,
                TokenizePipe::class,
                OperatorClassificationPipe::class,
                PhraseChunkingPipe::class,
                EntityExtractionPipe::class,
                IngredientNormalizationPipe::class,
                IntentDetectionPipe::class,
                ScoringProfilePipe::class,
                DSLBuilderPipe::class,
            ])
            ->thenReturn();

        return $result;
    }
}
