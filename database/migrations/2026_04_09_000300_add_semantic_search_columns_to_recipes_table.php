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
        DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS search_document TEXT');
        DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS semantic_embedding REAL[]');
        DB::statement("
            UPDATE recipes
            SET search_document = trim(
                concat_ws(
                    ' ',
                    normalized_title,
                    dish_type,
                    dietary,
                    ARRAY_TO_STRING(ingredients, ' ')
                )
            )
            WHERE search_document IS NULL
        ");
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_search_document_trgm ON recipes USING GIN (search_document gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_recipes_search_document_trgm');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS semantic_embedding');
        DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS search_document');
    }
};
