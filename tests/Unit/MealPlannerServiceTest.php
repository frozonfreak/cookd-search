<?php

use App\Models\Recipe;
use App\Services\MealPlannerService;
use Illuminate\Support\Collection;

it('builds a multi day meal plan from candidate recipes', function () {
    $planner = new MealPlannerService;

    $recipes = new Collection([
        new Recipe(['id' => 1, 'meal_type' => ['breakfast'], 'ingredients' => ['oats'], 'protein' => 12.0, 'calories' => 320.0, 'dietary' => 'vegetarian']),
        new Recipe(['id' => 2, 'meal_type' => ['lunch'], 'ingredients' => ['rice', 'tomato'], 'protein' => 22.0, 'calories' => 640.0, 'dietary' => 'vegetarian']),
        new Recipe(['id' => 3, 'meal_type' => ['dinner'], 'ingredients' => ['rice', 'paneer'], 'protein' => 26.0, 'calories' => 710.0, 'dietary' => 'vegetarian']),
    ]);

    $plan = $planner->plan([
        'days' => 2,
        'meals_per_day' => 3,
        'constraints' => [
            'calories_per_day' => 2000,
            'protein_min' => 60,
            'diet' => 'vegetarian',
        ],
    ], $recipes);

    expect($plan)->toHaveCount(2)
        ->and($plan[0]['meals'])->toHaveKeys(['breakfast', 'lunch', 'dinner']);
});
