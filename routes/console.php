<?php

use App\Services\RecipeIngestionService;
use App\Services\IngredientCatalogSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('recipes:ingest {--path=database/recipie} {--fresh}', function (RecipeIngestionService $ingestionService) {
    $path = base_path((string) $this->option('path'));

    $this->info("Ingesting recipes from {$path}");

    if ((bool) $this->option('fresh')) {
        $this->warn('Truncating recipes table before import');
        $ingestionService->truncate();
    }

    $result = $ingestionService->ingestDirectory($path);

    $this->table(
        ['processed', 'created', 'updated'],
        [[
            $result['processed'],
            $result['created'],
            $result['updated'],
        ]]
    );
})->purpose('Normalize and ingest recipe JSON files into PostgreSQL');

Artisan::command('ingredients:sync', function (IngredientCatalogSyncService $syncService) {
    $result = $syncService->sync();

    $this->table(
        ['ingredients', 'aliases'],
        [[
            $result['ingredients'],
            $result['aliases'],
        ]]
    );
})->purpose('Sync canonical ingredients and aliases from ingested recipes');
