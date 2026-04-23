<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/profiles', [\App\Http\Controllers\ProfileController::class, 'index']);
Route::get('/profiles/search', [\App\Http\Controllers\ProfileController::class, 'search']);
