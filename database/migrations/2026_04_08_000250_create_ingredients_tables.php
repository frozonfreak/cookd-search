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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->text('name')->unique();
            $table->timestamps();
        });

        Schema::create('ingredient_aliases', function (Blueprint $table) {
            $table->id();
            $table->text('alias')->unique();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_aliases');
        Schema::dropIfExists('ingredients');
    }
};
