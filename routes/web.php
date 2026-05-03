<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GroceryListController;
use App\Http\Controllers\RecipeSearchController;
use App\Http\Controllers\SubstitutionController;
use Illuminate\Support\Facades\Route;

Route::get('/', RecipeSearchController::class)->name('home');
Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
Route::post('/substitution', [SubstitutionController::class, 'suggest'])->name('substitution.suggest');
Route::post('/grocery-list', [GroceryListController::class, 'generate'])->name('grocery-list.generate');
