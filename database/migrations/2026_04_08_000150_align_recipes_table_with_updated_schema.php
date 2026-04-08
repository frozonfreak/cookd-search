<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_recipes_ingredients_gin');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_ingredient_ids_gin');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_meal_type_gin');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_cuisine_gin');
        DB::statement('DROP INDEX IF EXISTS recipes_normalized_title_index');
        DB::statement('DROP INDEX IF EXISTS recipes_dish_type_index');
        DB::statement('DROP INDEX IF EXISTS recipes_dietary_index');

        DB::statement('ALTER TABLE recipes ALTER COLUMN title TYPE TEXT');
        DB::statement('ALTER TABLE recipes ALTER COLUMN normalized_title TYPE TEXT');
        DB::statement('ALTER TABLE recipes ALTER COLUMN dish_type TYPE TEXT');
        DB::statement('ALTER TABLE recipes ALTER COLUMN dietary TYPE TEXT');

        DB::statement('ALTER TABLE recipes ADD COLUMN ingredients_new TEXT[]');
        DB::statement('ALTER TABLE recipes ADD COLUMN ingredient_ids_new INTEGER[]');

        DB::statement("
            UPDATE recipes
            SET ingredients_new = (
                SELECT COALESCE(array_agg(value), ARRAY[]::TEXT[])
                FROM jsonb_array_elements_text(ingredients)
            )
        ");

        DB::statement("
            UPDATE recipes
            SET ingredient_ids_new = (
                SELECT CASE
                    WHEN ingredient_ids IS NULL THEN NULL
                    ELSE COALESCE(array_agg(value::integer), ARRAY[]::INTEGER[])
                END
                FROM jsonb_array_elements_text(COALESCE(ingredient_ids, '[]'::jsonb))
            )
        ");

        DB::statement('ALTER TABLE recipes DROP COLUMN ingredients');
        DB::statement('ALTER TABLE recipes DROP COLUMN ingredient_ids');
        DB::statement('ALTER TABLE recipes RENAME COLUMN ingredients_new TO ingredients');
        DB::statement('ALTER TABLE recipes RENAME COLUMN ingredient_ids_new TO ingredient_ids');
        DB::statement('ALTER TABLE recipes ALTER COLUMN ingredients SET NOT NULL');

        DB::statement('ALTER TABLE recipes DROP CONSTRAINT IF EXISTS recipes_ingredients_not_empty');
        DB::statement('ALTER TABLE recipes DROP CONSTRAINT IF EXISTS recipes_cooking_time_non_negative');
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

        DB::statement("
            ALTER TABLE recipes
            ALTER COLUMN ingredients TYPE JSONB
            USING to_jsonb(ingredients)
        ");

        DB::statement("
            ALTER TABLE recipes
            ALTER COLUMN ingredient_ids TYPE JSONB
            USING to_jsonb(ingredient_ids)
        ");
    }
};
