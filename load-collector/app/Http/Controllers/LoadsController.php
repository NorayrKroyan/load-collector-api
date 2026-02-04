<?php

namespace App\Http\Controllers;

use App\Models\LoadImport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoadsController extends Controller
{
    public function push(Request $request): JsonResponse
    {
        // multipart/form-data:
        // - payload (required) : uploaded json file OR raw json string
        // - bolimage (optional): image file
        $request->validate([
            'payload'  => ['required'],
            'bolimage' => ['nullable', 'file', 'image', 'max:8192'], // 8MB
        ]);

        $dir = 'loadimports/' . now()->format('Y-m-d');

        // ----- Payload -----
        $payloadPath = null;
        $payloadOriginal = null;
        $payloadSize = null;
        $payloadJson = null;

        if ($request->file('payload')) {
            $f = $request->file('payload');
            $payloadOriginal = $f->getClientOriginalName();
            $payloadSize = $f->getSize();

            $name = Str::uuid()->toString() . '__' . ($payloadOriginal ?: 'payload.json');
            $payloadPath = $f->storeAs($dir, $name, 'local');

            $raw = Storage::disk('local')->get($payloadPath);
            $payloadJson = json_decode($raw, true);
        } else {
            $raw = (string) $request->input('payload');
            $payloadJson = json_decode($raw, true);

            $payloadOriginal = 'payload.json';
            $name = Str::uuid()->toString() . '__payload.json';
            $payloadPath = $dir . '/' . $name;

            Storage::disk('local')->put($payloadPath, $raw);
            $payloadSize = strlen($raw);
        }

        if ($payloadJson === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid JSON in payload',
            ], 422);
        }

        // jobname inside payload (optional)
        $jobname = is_array($payloadJson) ? ($payloadJson['jobname'] ?? null) : null;

        // ----- Optional image -----
        $imagePath = null;
        $imageOriginal = null;
        $imageSize = null;

        if ($request->file('bolimage')) {
            $img = $request->file('bolimage');
            $imageOriginal = $img->getClientOriginalName();
            $imageSize = $img->getSize();

            $imgName = Str::uuid()->toString() . '__' . ($imageOriginal ?: 'bolimage.jpg');
            $imagePath = $img->storeAs($dir, $imgName, 'local');
        }

        $row = LoadImport::create([
            'jobname'          => $jobname,

            'payload_path'     => $payloadPath,
            'payload_original' => $payloadOriginal,
            'payload_size'     => $payloadSize,

            'image_path'       => $imagePath,
            'image_original'   => $imageOriginal,
            'image_size'       => $imageSize,

            'payload_json'     => $payloadJson,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $row->id,
        ], 201);
    }
}
