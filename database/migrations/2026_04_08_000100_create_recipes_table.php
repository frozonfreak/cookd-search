<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->text('normalized_title')->nullable();
            $table->addColumn('text[]', 'ingredients');
            $table->addColumn('integer[]', 'ingredient_ids', ['nullable' => true]);
            $table->text('dish_type')->nullable();
            $table->jsonb('meal_type')->nullable();
            $table->jsonb('cuisine')->nullable();
            $table->integer('cooking_time')->nullable();
            $table->text('dietary')->nullable();
            $table->jsonb('raw_json');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE recipes ADD CONSTRAINT recipes_ingredients_not_empty CHECK (cardinality(ingredients) > 0)');
        DB::statement('ALTER TABLE recipes ADD CONSTRAINT recipes_cooking_time_non_negative CHECK (cooking_time IS NULL OR cooking_time >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE recipes DROP CONSTRAINT IF EXISTS recipes_cooking_time_non_negative');
        DB::statement('ALTER TABLE recipes DROP CONSTRAINT IF EXISTS recipes_ingredients_not_empty');

        Schema::dropIfExists('recipes');
    }
};
