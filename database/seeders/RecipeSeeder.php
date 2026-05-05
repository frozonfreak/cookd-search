<?php

namespace Database\Seeders;

use App\Services\IngredientCatalogSyncService;
use App\Services\RecipeIngestionService;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    // Paths checked in order — first one that exists wins.
    private const DATA_SOURCES = [
        'database/seeders/data/recipes.ndjson', // compact single-file (committed to git)
        'database/recipie',                     // raw per-recipe JSON directory (local dev)
    ];

    public function run(RecipeIngestionService $ingestion, IngredientCatalogSyncService $catalog): void
    {
        $source = $this->resolveSource();

        if ($source === null) {
            $this->command->warn('No recipe data found. Run `php artisan recipes:ingest` after placing JSON files in database/recipie/.');
            return;
        }

        $this->command->info("Source: {$source}");

        $this->command->info('Pass 1 — ingesting recipes…');
        $result = $ingestion->ingestFromSource($source);
        $this->command->info("  {$result['created']} created, {$result['updated']} updated ({$result['processed']} total)");

        $this->command->info('Syncing ingredient catalog…');
        $catalogResult = $catalog->sync();
        $this->command->info("  {$catalogResult['ingredients']} ingredients, {$catalogResult['aliases']} aliases");

        $this->command->info('Pass 2 — linking recipe↔ingredient rows…');
        $ingestion->ingestFromSource($source);
        $this->command->info('  Done.');
    }

    private function resolveSource(): ?string
    {
        foreach (self::DATA_SOURCES as $relative) {
            $full = base_path($relative);
            if (file_exists($full)) {
                return $full;
            }
        }

        return null;
    }
}
