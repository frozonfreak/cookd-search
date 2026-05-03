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
        Schema::create('user_pantry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->double('quantity_value')->default(0);
            $table->text('unit')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ingredient_id']);
            $table->index('expires_at');
        });

        Schema::create('ingredient_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->text('location')->nullable();
            $table->text('vendor')->nullable();
            $table->double('price_per_unit');
            $table->text('unit');
            $table->string('availability_status', 32)->default('available');
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['ingredient_id', 'location']);
            $table->index('availability_status');
        });

        Schema::create('grocery_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('meal_plan_id')->nullable();
            $table->string('status', 32)->default('draft');
            $table->double('estimated_total_cost')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('meal_plan_id');
        });

        Schema::create('grocery_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_list_id')->constrained('grocery_lists')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->double('required_quantity')->default(0);
            $table->double('pantry_quantity_used')->default(0);
            $table->double('quantity_to_buy')->default(0);
            $table->text('unit')->nullable();
            $table->double('estimated_cost')->default(0);
            $table->text('substitution_used')->nullable();
            $table->text('notes')->nullable();

            $table->index(['grocery_list_id', 'ingredient_id']);
        });

        Schema::create('recipe_ranking_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->text('query_text')->nullable();
            $table->jsonb('query_dsl')->nullable();
            $table->string('action', 32);
            $table->double('reward_score');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recipe_id', 'action']);
            $table->index(['user_id', 'created_at']);
        });

        DB::statement('CREATE INDEX IF NOT EXISTS idx_recipe_ranking_feedback_query_dsl_gin ON recipe_ranking_feedback USING GIN (query_dsl)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_recipe_ranking_feedback_query_dsl_gin');
        Schema::dropIfExists('recipe_ranking_feedback');
        Schema::dropIfExists('grocery_list_items');
        Schema::dropIfExists('grocery_lists');
        Schema::dropIfExists('ingredient_prices');
        Schema::dropIfExists('user_pantry_items');
    }
};
