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
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ingredients_gin ON recipes USING GIN (ingredients)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ingredient_ids_gin ON recipes USING GIN (ingredient_ids)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_dish_type ON recipes (dish_type)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cooking_time ON recipes (cooking_time)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_normalized_title ON recipes (normalized_title)');
        DB::statement("CREATE INDEX IF NOT EXISTS idx_title_fts ON recipes USING GIN (to_tsvector('english', title))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_title_fts');
        DB::statement('DROP INDEX IF EXISTS idx_normalized_title');
        DB::statement('DROP INDEX IF EXISTS idx_cooking_time');
        DB::statement('DROP INDEX IF EXISTS idx_dish_type');
        DB::statement('DROP INDEX IF EXISTS idx_ingredient_ids_gin');
        DB::statement('DROP INDEX IF EXISTS idx_ingredients_gin');
    }
};
