<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VectorSearchService
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @param  array<int, int>  $candidateIds
     * @return array<int, float>
     */
    public function search(array $queryEmbedding, array $candidateIds = [], int $limit = 20): array
    {
        if ($queryEmbedding === []) {
            return [];
        }

        if ($this->canUsePgvector()) {
            try {
                return $this->searchWithPgvector($queryEmbedding, $candidateIds, $limit);
            } catch (QueryException) {
                return $this->searchFallback($queryEmbedding, $candidateIds, $limit);
            }
        }

        return $this->searchFallback($queryEmbedding, $candidateIds, $limit);
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @param  array<int, int>  $candidateIds
     * @return array<int, float>
     */
    private function searchWithPgvector(array $queryEmbedding, array $candidateIds, int $limit): array
    {
        $query = Recipe::query()
            ->select('id')
            ->selectRaw('(1 - (embedding <=> ?::vector)) as semantic_score', [$this->embeddingService->toVectorLiteral($queryEmbedding)])
            ->whereNotNull('embedding')
            ->orderByDesc('semantic_score')
            ->limit($limit);

        if ($candidateIds !== []) {
            $query->whereIn('id', $candidateIds);
        }

        /** @var EloquentCollection<int, Recipe> $recipes */
        $recipes = $query->get();

        return $recipes->mapWithKeys(fn (Recipe $recipe) => [
            $recipe->id => round((float) ($recipe->semantic_score ?? 0.0), 4),
        ])->all();
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @param  array<int, int>  $candidateIds
     * @return array<int, float>
     */
    private function searchFallback(array $queryEmbedding, array $candidateIds, int $limit): array
    {
        $query = Recipe::query();

        if ($candidateIds !== []) {
            $query->whereIn('id', $candidateIds);
        }

        /** @var Collection<int, Recipe> $recipes */
        $recipes = $query->limit(max($limit * 2, 40))->get();

        return $recipes
            ->mapWithKeys(function (Recipe $recipe) use ($queryEmbedding): array {
                $embedding = $recipe->embedding ?? $recipe->semantic_embedding ?? [];

                if ($embedding === []) {
                    $embedding = $this->embeddingService->embedText($recipe->search_document ?? $recipe->title ?? '');
                }

                return [
                    $recipe->id => $this->embeddingService->cosineSimilarity($queryEmbedding, $embedding),
                ];
            })
            ->sortDesc()
            ->take($limit)
            ->all();
    }

    private function canUsePgvector(): bool
    {
        return DB::getDriverName() === 'pgsql'
            && Schema::hasTable('recipes')
            && Schema::hasColumn('recipes', 'embedding');
    }
}
