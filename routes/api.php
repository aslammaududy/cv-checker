<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EvaluateController;
use App\Http\Controllers\MilvusController;
use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::post('/upload', UploadController::class)->middleware('auth:sanctum');
Route::post('/evaluate', [EvaluateController::class, 'evaluate'])->middleware('auth:sanctum');
Route::get('/result/{id}', [EvaluateController::class, 'result'])->middleware('auth:sanctum');

// Milvus API routes
Route::prefix('milvus')->group(function () {
    Route::get('/test-connection', [MilvusController::class, 'testConnection']);
    Route::get('/collections', [MilvusController::class, 'listCollections']);
    Route::post('/collections', [MilvusController::class, 'createCollection']);
    Route::delete('/collections', [MilvusController::class, 'dropCollection']);
    Route::post('/vectors/insert-sample', [MilvusController::class, 'insertSampleVectors']);
    Route::post('/vectors/search', [MilvusController::class, 'searchSimilar']);
});
