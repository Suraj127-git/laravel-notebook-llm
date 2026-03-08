<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return response()->json(['message' => 'Please use API login endpoint'], 404);
});

Route::get('/register', function () {
    return response()->json(['message' => 'Please use API register endpoint'], 404);
});
