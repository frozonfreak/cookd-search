<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_taste_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->addColumn('text[]', 'preferred_ingredients', ['nullable' => true]);
            $table->addColumn('text[]', 'avoided_ingredients', ['nullable' => true]);
            $table->addColumn('text[]', 'preferred_dish_types', ['nullable' => true]);
            $table->addColumn('text[]', 'preferred_cuisines', ['nullable' => true]);
            $table->addColumn('real[]', 'taste_embedding', ['nullable' => true]);
            $table->float('weight')->default(0.85);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_taste_profiles');
    }
};
