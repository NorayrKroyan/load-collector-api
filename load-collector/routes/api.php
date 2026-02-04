<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportJobsController;
use App\Http\Controllers\LoadsController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/importjobs/list', [ImportJobsController::class, 'list']);
    Route::post('/loads/push', [LoadsController::class, 'push']);
});
