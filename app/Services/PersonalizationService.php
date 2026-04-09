<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\UserTasteProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PersonalizationService
{
    private const ACTION_WEIGHTS = [
        'view' => 0.15,
        'like' => 0.8,
        'cook' => 1.0,
        'skip' => -0.55,
    ];

    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {
    }

    /**
     * @return array{
     *     user_id:?int,
     *     taste_profile:array<string, float>,
     *     preferred_ingredients:array<int, string>,
     *     avoided_ingredients:array<int, string>,
     *     preferred_dish_types:array<int, string>,
     *     preferred_cuisines:array<int, string>,
     *     taste_preferences:array<string, float>,
     *     taste_embedding:array<int, float>,
     *     weight:float
     * }
     */
    public function resolveProfile(?User $user): array
    {
        if ($user === null) {
            return $this->emptyProfile();
        }

        $profile = $user->tasteProfile()->firstOrNew();
        $interactionTasteProfile = $this->buildTasteProfileFromInteractions($user);
        $storedTasteProfile = is_array($profile->taste_profile ?? null) ? $profile->taste_profile : [];
        $mergedTasteProfile = $this->mergeTasteProfiles($storedTasteProfile, $interactionTasteProfile);
        $tasteEmbedding = $profile->taste_embedding ?? [];

        if ($tasteEmbedding === [] && $mergedTasteProfile !== []) {
            $tasteEmbedding = $this->embeddingService->embedText($this->tasteProfileToText($mergedTasteProfile));
        }

        return [
            'user_id' => $user->id,
            'taste_profile' => $mergedTasteProfile,
            'preferred_ingredients' => array_values($profile->preferred_ingredients ?? []),
            'avoided_ingredients' => array_values($profile->avoided_ingredients ?? []),
            'preferred_dish_types' => array_values($profile->preferred_dish_types ?? []),
            'preferred_cuisines' => array_values($profile->preferred_cuisines ?? []),
            'taste_preferences' => $mergedTasteProfile,
            'taste_embedding' => $tasteEmbedding,
            'weight' => $mergedTasteProfile === [] ? 0.0 : (float) ($profile->weight ?? 0.85),
        ];
    }

    public function recordInteraction(User $user, Recipe $recipe, string $action, ?CarbonImmutable $at = null): void
    {
        $timestamp = $at ?? CarbonImmutable::now();

        UserInteraction::query()->create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'action' => $action,
            'created_at' => $timestamp,
        ]);

        $profile = UserTasteProfile::query()->firstOrNew(['user_id' => $user->id]);
        $updatedTasteProfile = $this->mergeTasteProfiles(
            is_array($profile->taste_profile ?? null) ? $profile->taste_profile : [],
            $this->scoreTasteInteraction($recipe, $action, 1.0)
        );

        $profile->forceFill([
            'taste_profile' => $updatedTasteProfile,
            'taste_embedding' => $this->embeddingService->embedText($this->tasteProfileToText($updatedTasteProfile)),
            'weight' => 0.9,
        ])->save();
    }

    /**
     * @param  Collection<int, Recipe>  $recipes
     * @return Collection<int, Recipe>
     */
    public function apply(Collection $recipes, array $profile): Collection
    {
        if ($recipes->isEmpty() || ($profile['weight'] ?? 0.0) <= 0.0) {
            return $recipes;
        }

        return $recipes->map(function (Recipe $recipe) use ($profile): Recipe {
            $score = $this->scoreRecipe($recipe, $profile);
            $recipe->setAttribute('personalization_score', round($score, 4));

            return $recipe;
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function scoreRecipe(Recipe $recipe, array $profile): float
    {
        $recipeTaste = is_array($recipe->taste_profile) ? $recipe->taste_profile : [];
        $userTaste = $profile['taste_profile'] ?? [];

        if ($recipeTaste === [] || $userTaste === []) {
            return 0.0;
        }

        $components = [];

        foreach ($userTaste as $dimension => $preferredValue) {
            $components[] = max(0.0, 1 - abs((float) ($recipeTaste[$dimension] ?? 0.0) - (float) $preferredValue));
        }

        return $components === [] ? 0.0 : round(array_sum($components) / count($components), 4);
    }

    /**
     * @return array<string, float>
     */
    private function buildTasteProfileFromInteractions(User $user): array
    {
        $interactions = UserInteraction::query()
            ->with('recipe:id,taste_profile')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(200)
            ->get();

        $profile = [];
        $now = CarbonImmutable::now();

        foreach ($interactions as $interaction) {
            $ageInDays = max(0, $interaction->created_at?->diffInDays($now) ?? 0);
            $decay = exp(-0.035 * $ageInDays);
            $tasteProfile = is_array($interaction->recipe?->taste_profile) ? $interaction->recipe->taste_profile : [];
            $delta = $this->scoreTasteInteraction($interaction->recipe, $interaction->action, $decay);

            foreach ($delta as $dimension => $value) {
                $profile[$dimension] = ($profile[$dimension] ?? 0.0) + $value;
            }

            foreach ($tasteProfile as $dimension => $value) {
                if (! array_key_exists($dimension, $profile)) {
                    $profile[$dimension] = 0.0;
                }
            }
        }

        return array_map(
            static fn ($value): float => round(max(0.0, min(1.0, 0.5 + (float) $value)), 4),
            array_filter($profile, static fn ($value): bool => abs((float) $value) > 0.001)
        );
    }

    /**
     * @return array<string, float>
     */
    private function scoreTasteInteraction(?Recipe $recipe, string $action, float $weight): array
    {
        if ($recipe === null || ! is_array($recipe->taste_profile)) {
            return [];
        }

        $multiplier = self::ACTION_WEIGHTS[$action] ?? 0.0;

        return collect($recipe->taste_profile)
            ->filter(fn ($value) => is_numeric($value))
            ->mapWithKeys(fn ($value, $dimension) => [$dimension => round(((float) $value - 0.5) * $multiplier * $weight, 4)])
            ->all();
    }

    /**
     * @param  array<string, float>  $stored
     * @param  array<string, float>  $derived
     * @return array<string, float>
     */
    private function mergeTasteProfiles(array $stored, array $derived): array
    {
        $keys = array_values(array_unique([...array_keys($stored), ...array_keys($derived)]));
        $merged = [];

        foreach ($keys as $key) {
            $merged[$key] = round(max(0.0, min(1.0, (($stored[$key] ?? 0.0) * 0.55) + (($derived[$key] ?? 0.0) * 0.45))), 4);
        }

        return array_filter($merged, static fn ($value): bool => (float) $value > 0.0);
    }

    /**
     * @param  array<string, float>  $profile
     */
    private function tasteProfileToText(array $profile): string
    {
        return collect($profile)
            ->sortKeys()
            ->map(fn ($value, $key) => Str::repeat((string) $key.' ', max(1, (int) round($value * 5))))
            ->implode(' ');
    }

    /**
     * @return array{
     *     user_id:null,
     *     taste_profile:array<string, float>,
     *     preferred_ingredients:array<int, string>,
     *     avoided_ingredients:array<int, string>,
     *     preferred_dish_types:array<int, string>,
     *     preferred_cuisines:array<int, string>,
     *     taste_preferences:array<string, float>,
     *     taste_embedding:array<int, float>,
     *     weight:float
     * }
     */
    private function emptyProfile(): array
    {
        return [
            'user_id' => null,
            'taste_profile' => [],
            'preferred_ingredients' => [],
            'avoided_ingredients' => [],
            'preferred_dish_types' => [],
            'preferred_cuisines' => [],
            'taste_preferences' => [],
            'taste_embedding' => [],
            'weight' => 0.0,
        ];
    }
}
