<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ImportJobsController extends Controller
{
    public function list(): JsonResponse
    {
        // Read from MySQL VIEW (no more fake_jobs table)
        $rows = DB::table('view_propx_import_jobs')->get(['job_name']);

        // { jobname, signature: [] }
        $items = $rows->map(fn ($r) => [
            'jobname'    => $r->job_name,
            'signature'  => [],
        ])->values();

        return response()->json(['items' => $items]);
    }
}
