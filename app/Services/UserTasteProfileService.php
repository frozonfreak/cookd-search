<?php

namespace App\Services;

use App\Models\User;

class UserTasteProfileService
{
    public function __construct(
        private readonly PersonalizationService $personalizationService,
    ) {
    }

    /**
     * @return array{
     *     user_id:?int,
     *     preferred_ingredients:array<int, string>,
     *     avoided_ingredients:array<int, string>,
     *     preferred_dish_types:array<int, string>,
     *     preferred_cuisines:array<int, string>,
     *     taste_preferences:array<string, float>,
     *     taste_embedding:array<int, float>,
     *     weight:float
     * }
     */
    public function resolve(?User $user): array
    {
        return $this->personalizationService->resolveProfile($user);
    }
}
