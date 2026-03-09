<?php

use App\Http\Controllers\ImportJobsController;
use App\Http\Controllers\LoadsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Load Collection API Routes
|--------------------------------------------------------------------------
|
| These routes form the external machine-to-machine contract used by the
| LoadImport ingestion flow.
|
| Authentication model:
| - all routes in this group require a valid Laravel Sanctum Bearer token
| - token issuance is handled operationally by the custom artisan command
|   `php artisan app:issue-token ...`
|
| Current endpoints:
| - GET  /api/importjobs/list
|     Returns the list of jobs the remote collector should poll.
|
| - POST /api/loads/push
|     Accepts one JSON payload and an optional BOL image, stores files on disk,
|     and inserts one row into `loadimports`.
|
| Important:
| The route URLs are part of the external collector contract. They should be
| treated as stable unless all remote clients are updated together.
|
*/
Route::middleware('auth:sanctum')->group(function () {
    // Return the list of jobs available for polling.
    Route::get('/importjobs/list', [ImportJobsController::class, 'list']);

    // Accept an uploaded load package and archive it into `loadimports`.
    Route::post('/loads/push', [LoadsController::class, 'push']);
});
