<?php

use App\Http\Controllers\RecipeSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', RecipeSearchController::class)->name('home');
