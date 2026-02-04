<?php

namespace App\Http\Controllers;

use App\Models\FakeJob;
use Illuminate\Http\JsonResponse;

class ImportJobsController extends Controller
{
    public function list(): JsonResponse
    {
        $items = FakeJob::query()
            ->orderBy('id')
            ->get(['jobname', 'signature']);

        return response()->json(['items' => $items]);
    }
}
