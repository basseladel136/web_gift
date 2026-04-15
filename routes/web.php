<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
Route::middleware('auth:sanctum')->group(function () {
   
// protected routes
});
});
