<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_taste_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->float('weight')->default(0.85);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE user_taste_profiles ADD COLUMN preferred_ingredients TEXT[] NULL');
        DB::statement('ALTER TABLE user_taste_profiles ADD COLUMN avoided_ingredients TEXT[] NULL');
        DB::statement('ALTER TABLE user_taste_profiles ADD COLUMN preferred_dish_types TEXT[] NULL');
        DB::statement('ALTER TABLE user_taste_profiles ADD COLUMN preferred_cuisines TEXT[] NULL');
        DB::statement('ALTER TABLE user_taste_profiles ADD COLUMN taste_embedding REAL[] NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_taste_profiles');
    }
};
