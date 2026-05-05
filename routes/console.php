<?php

use App\Services\IngredientCatalogSyncService;
use App\Services\RecipeIngestionService;
use App\Services\RecipeSqliteExportService;
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

    $result = $ingestionService->ingestFromSource($path);

    $this->table(
        ['processed', 'created', 'updated'],
        [[
            $result['processed'],
            $result['created'],
            $result['updated'],
        ]]
    );
})->purpose('Normalize and ingest recipe JSON files or an NDJSON file into PostgreSQL');

Artisan::command('recipes:build-seed {--source=database/recipie} {--output=database/seeders/data/recipes.ndjson}', function () {
    $source = base_path((string) $this->option('source'));
    $output = base_path((string) $this->option('output'));

    if (! is_dir($source)) {
        $this->error("Source directory not found: {$source}");
        return 1;
    }

    $outputDir = dirname($output);
    if (! is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $files = glob($source . '/*.json');
    if ($files === false || count($files) === 0) {
        $this->error('No JSON files found in source directory.');
        return 1;
    }

    $handle = fopen($output, 'w');
    if ($handle === false) {
        $this->error("Cannot write to {$output}");
        return 1;
    }

    $count = 0;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            $count++;
        } catch (JsonException) {
            $this->warn("Skipping invalid JSON: {$file}");
        }
    }

    fclose($handle);

    $size = round(filesize($output) / 1024 / 1024, 2);
    $this->info("Wrote {$count} recipes to {$output} ({$size} MB)");
    return 0;
})->purpose('Pack all per-recipe JSON files into a single NDJSON seed file for deployment');

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

Artisan::command('recipes:export-sqlite {--output=storage/app/cookd_recipes.sqlite} {--fresh}', function (RecipeSqliteExportService $exportService) {
    $output = (string) $this->option('output');

    $this->info("Exporting recipes to {$output}");

    $result = $exportService->export(
        output: $output,
        fresh: (bool) $this->option('fresh'),
    );

    $this->table(
        ['metric', 'value'],
        collect($result)
            ->map(fn ($value, $key): array => [$key, $value])
            ->values()
            ->all(),
    );
})->purpose('Export imported recipe data into a bundled SQLite database');
