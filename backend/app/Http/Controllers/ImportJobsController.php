<?php

namespace App\Http\Controllers;

use App\Models\LoadImportJob;
use Illuminate\Http\JsonResponse;

class ImportJobsController extends Controller
{
    /**
     * Return the list of import jobs that the external collector should process.
     *
     * Final logic:
     * - source of truth is the physical MySQL table `loadimport_jobs`
     * - this endpoint does not read from any SQL view
     *
     * Response shape:
     * {
     *   "items": [
     *     {
     *       "jobname": "Example Job",
     *       "signature": []
     *     }
     *   ]
     * }
     */
    public function list(): JsonResponse
    {
        $rows = LoadImportJob::query()
            ->orderBy('jobname')
            ->get(['jobname', 'signature']);

        $items = $rows->map(fn (LoadImportJob $row) => [
            'jobname' => $row->jobname,
            'signature' => is_array($row->signature) ? $row->signature : [],
        ])->values();

        return response()->json([
            'items' => $items,
        ]);
    }
}