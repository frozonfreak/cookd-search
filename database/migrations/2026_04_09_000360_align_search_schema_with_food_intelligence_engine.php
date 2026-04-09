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
        DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS calories DOUBLE PRECISION');
        DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS fat DOUBLE PRECISION');
        DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS protein DOUBLE PRECISION');
        DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS sodium DOUBLE PRECISION');
        DB::statement("ALTER TABLE recipes ADD COLUMN IF NOT EXISTS nutrition JSONB DEFAULT '{}'::jsonb");
        DB::statement("ALTER TABLE recipes ADD COLUMN IF NOT EXISTS taste_profile JSONB DEFAULT '{}'::jsonb");

        DB::statement('ALTER TABLE ingredients ADD COLUMN IF NOT EXISTS group_id BIGINT NULL');

        DB::statement('
            CREATE TABLE IF NOT EXISTS recipe_ingredients (
                recipe_id BIGINT NOT NULL,
                ingredient_id BIGINT NOT NULL,
                quantity_value DOUBLE PRECISION NULL,
                quantity_text TEXT NULL,
                unit TEXT NULL,
                PRIMARY KEY (recipe_id, ingredient_id),
                CONSTRAINT recipe_ingredients_recipe_id_foreign
                    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
                CONSTRAINT recipe_ingredients_ingredient_id_foreign
                    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
            )
        ');

        DB::statement("
            CREATE TABLE IF NOT EXISTS ingredient_relations (
                ingredient_id BIGINT NOT NULL,
                related_ingredient_id BIGINT NOT NULL,
                relation_type TEXT NOT NULL,
                strength DOUBLE PRECISION NOT NULL DEFAULT 1,
                PRIMARY KEY (ingredient_id, related_ingredient_id, relation_type),
                CONSTRAINT ingredient_relations_ingredient_id_foreign
                    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
                CONSTRAINT ingredient_relations_related_ingredient_id_foreign
                    FOREIGN KEY (related_ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
            )
        ");

        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_calories ON recipes (calories)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_fat ON recipes (fat)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_protein ON recipes (protein)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_sodium ON recipes (sodium)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_meal_type_gin_v2 ON recipes USING GIN (meal_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_cuisine_gin_v2 ON recipes USING GIN (cuisine)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_nutrition_gin ON recipes USING GIN (nutrition)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_taste_profile_gin ON recipes USING GIN (taste_profile)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipe_ingredients_ingredient ON recipe_ingredients (ingredient_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipe_ingredients_quantity ON recipe_ingredients (quantity_value)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ingredient_relations_lookup ON ingredient_relations (ingredient_id, relation_type)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_ingredient_relations_lookup');
        DB::statement('DROP INDEX IF EXISTS idx_recipe_ingredients_quantity');
        DB::statement('DROP INDEX IF EXISTS idx_recipe_ingredients_ingredient');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_taste_profile_gin');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_nutrition_gin');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_cuisine_gin_v2');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_meal_type_gin_v2');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_sodium');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_protein');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_fat');
        DB::statement('DROP INDEX IF EXISTS idx_recipes_calories');

        DB::statement('DROP TABLE IF EXISTS ingredient_relations');
        DB::statement('DROP TABLE IF EXISTS recipe_ingredients');

        DB::statement('ALTER TABLE ingredients DROP COLUMN IF EXISTS group_id');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS taste_profile');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS nutrition');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS sodium');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS protein');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS fat');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS calories');
    }
};
