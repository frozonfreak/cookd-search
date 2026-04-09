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
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {
    }

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
                $recipeIngredients = $record['recipe_ingredients'];
                unset($record['recipe_ingredients']);

                $recipe = Recipe::query()->find($record['id']);

                if ($recipe === null) {
                    $recipe = new Recipe;
                    $created++;
                } else {
                    $updated++;
                }

                $recipe->forceFill($record)->save();
                $this->syncRecipeIngredients($recipe->id, $recipeIngredients);

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

        $dishType = $this->resolveDishType($payload);
        $mealType = $this->normalizeList($payload['meal_times'] ?? null);
        $cuisine = $this->normalizeList([
            ...($payload['cuisines_name'] ?? []),
            ...($payload['sub_cuisines_name'] ?? []),
        ]);
        $dietary = $this->normalizeNullableString($payload['dietary_restriction'] ?? null);
        $searchDocument = $this->buildSearchDocument(
            title: (string) ($payload['title'] ?? ''),
            ingredients: $ingredients,
            dishType: $dishType,
            mealType: $mealType,
            cuisine: $cuisine,
            dietary: $dietary,
        );

        return [
            'id' => (int) ($payload['id'] ?? 0),
            'title' => (string) ($payload['title'] ?? ''),
            'normalized_title' => Str::of((string) ($payload['title'] ?? ''))->lower()->squish()->value(),
            'ingredients' => $ingredients,
            'ingredient_ids' => $ingredientIds,
            'dish_type' => $dishType,
            'meal_type' => $mealType,
            'cuisine' => $cuisine,
            'cooking_time' => $this->normalizeNullableInt($payload['cooking_time'] ?? null),
            'dietary' => $dietary,
            'calories' => $this->extractNutritionValue($payload, 'calories'),
            'fat' => $this->extractNutritionValue($payload, 'fat'),
            'protein' => $this->extractNutritionValue($payload, 'protein'),
            'sodium' => $this->extractNutritionValue($payload, 'sodium'),
            'nutrition' => $this->extractNutritionPayload($payload),
            'taste_profile' => $this->extractTasteProfile($payload),
            'search_document' => $searchDocument,
            'embedding' => $this->embeddingService->embedText($searchDocument),
            'semantic_embedding' => $this->embeddingService->embedText($searchDocument),
            'recipe_ingredients' => $this->mapRecipeIngredients($payload['recipe_ingredients'] ?? []),
            'raw_json' => $payload,
        ];
    }

    /**
     * @param  array<int, string>  $ingredients
     * @param  array<int, string>|null  $mealType
     * @param  array<int, string>|null  $cuisine
     */
    private function buildSearchDocument(
        string $title,
        array $ingredients,
        ?string $dishType,
        ?array $mealType,
        ?array $cuisine,
        ?string $dietary,
    ): string {
        return Str::of(implode(' ', array_filter([
            $title,
            $dishType,
            $dietary,
            $mealType !== null ? implode(' ', $mealType) : null,
            $cuisine !== null ? implode(' ', $cuisine) : null,
            implode(' ', $ingredients),
        ])))
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value();
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractNutritionValue(array $payload, string $key): ?float
    {
        $value = data_get($payload, $key, data_get($payload, 'nutrition.'.$key));

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    private function extractNutritionPayload(array $payload): array
    {
        $nutrition = data_get($payload, 'nutrition', []);

        if (! is_array($nutrition)) {
            $nutrition = [];
        }

        foreach (['calories', 'fat', 'protein', 'sodium'] as $key) {
            $value = $this->extractNutritionValue($payload, $key);

            if ($value !== null) {
                $nutrition[$key] = $value;
            }
        }

        return $nutrition;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    private function extractTasteProfile(array $payload): array
    {
        $profile = data_get($payload, 'taste_profile', []);

        if (is_array($profile) && $profile !== []) {
            return array_map('floatval', array_filter($profile, fn ($value) => is_numeric($value)));
        }

        $title = Str::lower((string) ($payload['title'] ?? ''));

        return array_filter([
            'spicy' => Str::contains($title, ['spicy', 'masala']) ? 0.7 : null,
            'tangy' => Str::contains($title, ['tangy', 'chutney']) ? 0.55 : null,
            'sweet' => Str::contains($title, ['sweet', 'dessert']) ? 0.65 : null,
            'rich' => Str::contains($title, ['butter', 'creamy']) ? 0.6 : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  iterable<mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapRecipeIngredients(iterable $rows): array
    {
        return Collection::make($rows)
            ->map(function ($row): ?array {
                if (! is_array($row) || ! isset($row['ingredient_id'])) {
                    return null;
                }

                $quantityValue = $this->normalizeQuantityValue($row);

                return [
                    'ingredient_id' => (int) $row['ingredient_id'],
                    'quantity_value' => $quantityValue,
                    'quantity_text' => isset($row['quantity']) && is_string($row['quantity']) ? trim($row['quantity']) : null,
                    'unit' => isset($row['unit']) && is_string($row['unit']) ? trim($row['unit']) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function normalizeQuantityValue(array $row): ?float
    {
        $value = $row['quantity_value'] ?? $row['normalized_quantity'] ?? null;

        if (is_numeric($value)) {
            return max(0.0, min(1.0, (float) $value));
        }

        $quantityText = Str::lower((string) ($row['quantity'] ?? ''));

        return match (true) {
            $quantityText === '' => null,
            Str::contains($quantityText, ['little', 'small']) => 0.2,
            Str::contains($quantityText, ['extra', 'more']) => 0.8,
            default => 0.5,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncRecipeIngredients(int $recipeId, array $rows): void
    {
        DB::table('recipe_ingredients')->where('recipe_id', $recipeId)->delete();

        if ($rows === []) {
            return;
        }

        DB::table('recipe_ingredients')->insert(array_map(
            static fn (array $row): array => [
                'recipe_id' => $recipeId,
                'ingredient_id' => $row['ingredient_id'],
                'quantity_value' => $row['quantity_value'],
                'quantity_text' => $row['quantity_text'],
                'unit' => $row['unit'],
            ],
            $rows
        ));
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
