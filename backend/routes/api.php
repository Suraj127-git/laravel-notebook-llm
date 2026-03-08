<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\NotebookController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\StreamAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    // User settings
    Route::patch('/user', [UserController::class, 'update']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);
    Route::delete('/user', [UserController::class, 'destroy']);

    // Notebooks
    Route::get('/notebooks', [NotebookController::class, 'index']);
    Route::post('/notebooks', [NotebookController::class, 'store']);
    Route::get('/notebooks/{notebook}', [NotebookController::class, 'show']);
    Route::patch('/notebooks/{notebook}', [NotebookController::class, 'update']);
    Route::delete('/notebooks/{notebook}', [NotebookController::class, 'destroy']);

    // Documents
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);

    // Chat
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::get('/chat/history/{notebookId}', [ChatController::class, 'history']);

    // AI Tools
    Route::post('/images/generate', [ImageController::class, 'generate']);
    Route::post('/audio/transcribe', [AudioController::class, 'transcribe']);

    // Usage statistics
    Route::get('/usage', [UserController::class, 'usage']);
});

// Streaming route with custom auth middleware
Route::match(['get', 'post'], '/chat/stream', [ChatController::class, 'stream'])->middleware('stream.auth');

