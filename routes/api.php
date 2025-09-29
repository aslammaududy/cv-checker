<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EvaluateController;
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
