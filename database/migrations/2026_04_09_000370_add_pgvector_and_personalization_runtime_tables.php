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
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
            DB::statement('ALTER TABLE recipes ADD COLUMN IF NOT EXISTS embedding vector(1536)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_recipes_embedding ON recipes USING ivfflat (embedding vector_cosine_ops)');
        }

        if (! Schema::hasColumn('user_taste_profiles', 'taste_profile')) {
            DB::statement("ALTER TABLE user_taste_profiles ADD COLUMN taste_profile JSONB DEFAULT '{}'::jsonb");
        }

        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->string('action', 32);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS idx_user_interactions_user_created_at ON user_interactions (user_id, created_at DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_user_interactions_recipe_action ON user_interactions (recipe_id, action)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_user_interactions_recipe_action');
        DB::statement('DROP INDEX IF EXISTS idx_user_interactions_user_created_at');
        Schema::dropIfExists('user_interactions');

        if (Schema::hasColumn('user_taste_profiles', 'taste_profile')) {
            DB::statement('ALTER TABLE user_taste_profiles DROP COLUMN taste_profile');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_recipes_embedding');
            DB::statement('ALTER TABLE recipes DROP COLUMN IF EXISTS embedding');
        }
    }
};
