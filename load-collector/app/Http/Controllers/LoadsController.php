<?php

namespace App\Http\Controllers;

use App\Models\LoadImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoadsController extends Controller
{
    /**
     * Central storage disk used by this ingestion endpoint.
     *
     * This stays hard-coded to preserve the current production behavior exactly.
     * Future refactors could move this into config/env, but that is intentionally
     * not done here to avoid changing runtime behavior during handoff.
     */
    private const STORAGE_DISK = 'local';

    /**
     * Top-level directory under the selected storage disk.
     *
     * Final writes go into:
     *   loadimports/YYYY-MM-DD/<generated-file-name>
     *
     * Example stored relative path:
     *   loadimports/2026-03-09/uuid__order.json
     */
    private const STORAGE_BASE_DIR = 'loadimports';

    /**
     * Accept one incoming load package and store it for downstream processing.
     *
     * Expected request type:
     * - multipart/form-data
     *
     * Accepted fields:
     * - payload  (required)
     *      Can be either:
     *      1. an uploaded JSON file
     *      2. a raw JSON string sent in the form field
     *
     * - bolimage (optional)
     *      An uploaded image file associated with the load/BOL.
     *
     * Primary responsibilities of this method:
     * 1. validate the request
     * 2. determine the daily storage directory
     * 3. persist the payload file (or generate one from raw JSON)
     * 4. decode JSON and store it into `payload_json`
     * 5. optionally persist the image file
     * 6. insert one row into the `loadimports` table
     * 7. return the inserted row ID
     *
     * Important design choice:
     * The payload is stored in two ways on purpose:
     * - as a file on disk (`payload_path`) for audit/replay/debug purposes
     * - as decoded JSON in the database (`payload_json`) for quick inspection and search
     */
    public function push(Request $request): JsonResponse
    {
        // Validate the request before performing any application logic.
        // `payload` is mandatory because this API is fundamentally a payload ingestion endpoint.
        // `bolimage` is optional and capped at 8 MB to avoid unexpectedly large uploads.
        $request->validate([
            'payload' => ['required'],
            'bolimage' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        // Store incoming files under a date-partitioned folder.
        // This keeps raw ingestion files organized by day and simplifies manual inspection.
        $dir = self::STORAGE_BASE_DIR . '/' . now()->format('Y-m-d');

        // ---------------------------
        // Payload bookkeeping fields
        // ---------------------------
        // These values will eventually be persisted into `loadimports`.
        $payloadPath = null;
        $payloadOriginal = null;
        $payloadSize = null;
        $payloadJson = null;

        if ($request->file('payload')) {
            // ------------------------------------------------------------
            // Mode A: `payload` was uploaded as a physical JSON file.
            // ------------------------------------------------------------
            $f = $request->file('payload');
            $payloadOriginal = $f->getClientOriginalName();
            $payloadSize = $f->getSize();

            // Prefix the original name with a UUID to avoid filename collisions.
            $name = Str::uuid()->toString() . '__' . ($payloadOriginal ?: 'payload.json');

            // Store the file on the configured disk and keep only the relative path in DB.
            $payloadPath = $f->storeAs($dir, $name, self::STORAGE_DISK);

            // Read the stored file back from disk and decode it to structured JSON.
            // This ensures the database has a searchable JSON copy of the exact stored content.
            $raw = Storage::disk(self::STORAGE_DISK)->get($payloadPath);
            $payloadJson = json_decode($raw, true);
        } else {
            // ------------------------------------------------------------
            // Mode B: `payload` was provided as a raw form value instead of a file.
            // ------------------------------------------------------------
            $rawInput = $request->input('payload');

            // Support both cases:
            // - client sends a JSON string directly
            // - framework/input normalization already turned the payload into an array
            $raw = is_array($rawInput)
                ? json_encode($rawInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (string) $rawInput;

            $payloadJson = json_decode($raw, true);

            // For consistency, even non-file payloads are still written to disk so the ingestion
            // system always has a file artifact corresponding to each database row.
            $payloadOriginal = 'payload.json';
            $name = Str::uuid()->toString() . '__payload.json';
            $payloadPath = $dir . '/' . $name;

            Storage::disk(self::STORAGE_DISK)->put($payloadPath, $raw);
            $payloadSize = strlen($raw);
        }

        // Reject malformed JSON. The endpoint is specifically for JSON load payloads,
        // so it is better to fail fast than to store ambiguous/unusable data.
        //
        // Note:
        // json_decode('null', true) returns null with JSON_ERROR_NONE, which is valid JSON.
        // This guard intentionally only rejects true decode errors.
        if ($payloadJson === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid JSON in payload',
            ], 422);
        }

        // Extract `jobname` from the top-level JSON object when present.
        // This value is duplicated into a first-class column because it is operationally useful
        // for browsing/filtering rows without opening the full JSON.
        $jobname = is_array($payloadJson) ? ($payloadJson['jobname'] ?? null) : null;

        // --------------------------
        // Optional image bookkeeping
        // --------------------------
        $imagePath = null;
        $imageOriginal = null;
        $imageSize = null;

        if ($request->file('bolimage')) {
            // Persist the optional BOL image to the same date-based directory as the payload.
            // Keeping related files together simplifies support and downstream reconciliation.
            $img = $request->file('bolimage');
            $imageOriginal = $img->getClientOriginalName();
            $imageSize = $img->getSize();

            $imgName = Str::uuid()->toString() . '__' . ($imageOriginal ?: 'bolimage.jpg');
            $imagePath = $img->storeAs($dir, $imgName, self::STORAGE_DISK);
        }

        // Insert one row that represents one ingestion event.
        // This table is intentionally append-only in behavior from the controller perspective:
        // every accepted API call results in a fresh row.
        $row = LoadImport::create([
            'jobname' => $jobname,

            'payload_path' => $payloadPath,
            'payload_original' => $payloadOriginal,
            'payload_size' => $payloadSize,

            'image_path' => $imagePath,
            'image_original' => $imageOriginal,
            'image_size' => $imageSize,

            'payload_json' => $payloadJson,
        ]);

        // Return the inserted row ID so the client or operator can trace the upload later.
        return response()->json([
            'ok' => true,
            'id' => $row->id,
        ], 201);
    }
}
