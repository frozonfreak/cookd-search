<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class RecipeIngestionService
{
    /**
     * @return array{processed:int, created:int, updated:int}
     */
    public function ingestDirectory(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            throw new RuntimeException("Recipe directory not found: {$directory}");
        }

        $processed = 0;
        $created = 0;
        $updated = 0;

        /** @var array<int, string> $files */
        $files = File::files($directory);

        collect($files)
            ->sortBy(fn ($file) => $file->getFilename())
            ->each(function ($file) use (&$processed, &$created, &$updated): void {
                $record = $this->mapRecipe($this->decodeFile($file->getPathname()));

                $recipe = Recipe::query()->find($record['id']);

                if ($recipe === null) {
                    $recipe = new Recipe;
                    $created++;
                } else {
                    $updated++;
                }

                $recipe->forceFill($record)->save();

                $processed++;
            });

        $this->syncPrimaryKeySequence();

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function truncate(): void
    {
        DB::table('recipes')->truncate();
        $this->syncPrimaryKeySequence();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFile(string $path): array
    {
        try {
            /** @var array<string, mixed> */
            return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON in {$path}", previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mapRecipe(array $payload): array
    {
        $ingredientRows = collect($payload['recipe_ingredients'] ?? []);

        $ingredients = $ingredientRows
            ->pluck('ingredient_name')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $this->normalizeLabel($value))
            ->unique()
            ->values()
            ->all();

        $ingredientIds = $ingredientRows
            ->pluck('ingredient_id')
            ->filter(fn ($value) => is_int($value) || ctype_digit((string) $value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        return [
            'id' => (int) ($payload['id'] ?? 0),
            'title' => (string) ($payload['title'] ?? ''),
            'normalized_title' => Str::of((string) ($payload['title'] ?? ''))->lower()->squish()->value(),
            'ingredients' => $ingredients,
            'ingredient_ids' => $ingredientIds,
            'dish_type' => $this->resolveDishType($payload),
            'meal_type' => $this->normalizeList($payload['meal_times'] ?? null),
            'cuisine' => $this->normalizeList([
                ...($payload['cuisines_name'] ?? []),
                ...($payload['sub_cuisines_name'] ?? []),
            ]),
            'cooking_time' => $this->normalizeNullableInt($payload['cooking_time'] ?? null),
            'dietary' => $this->normalizeNullableString($payload['dietary_restriction'] ?? null),
            'raw_json' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDishType(array $payload): ?string
    {
        $dishCandidates = $this->normalizeList([
            ...($payload['sub_meals_name'] ?? []),
            ...($payload['meal_courses_name'] ?? []),
        ]);

        if (! empty($dishCandidates)) {
            return $dishCandidates[0];
        }

        $title = Str::of((string) ($payload['title'] ?? ''))->lower();

        foreach (['chutney', 'curry', 'gravy', 'rice', 'biryani', 'fry'] as $keyword) {
            if ($title->contains($keyword)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * @param  iterable<mixed>|null  $values
     * @return array<int, string>|null
     */
    private function normalizeList(iterable|null $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $normalized = Collection::make($values)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => $this->normalizeLabel($value))
            ->unique()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeLabel(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->squish()
            ->value();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $this->normalizeLabel($value);
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function syncPrimaryKeySequence(): void
    {
        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('recipes', 'id'),
                COALESCE((SELECT MAX(id) FROM recipes), 1),
                (SELECT EXISTS(SELECT 1 FROM recipes))
            )
        ");
    }
}
