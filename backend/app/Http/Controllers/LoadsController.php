<?php

namespace App\Http\Controllers;

use App\Models\LoadImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LoadsController extends Controller
{
    /**
     * Storage disk used for archived incoming files.
     */
    private const STORAGE_DISK = 'local';

    /**
     * Base folder for archived payloads/images.
     * Example: loadimports/2026-03-09/uuid__payload.json
     */
    private const STORAGE_BASE_DIR = 'loadimports';

    /**
     * Accept one incoming load package and store it for downstream processing.
     *
     * Supported payload modes:
     * 1. uploaded JSON file
     * 2. raw JSON string form field
     */
    public function push(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();

        Log::info('[loads.push] request received', $this->baseLogContext($request, $requestId));

        $validator = Validator::make($request->all(), [
            'payload' => ['required'],
            'bolimage' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        if ($validator->fails()) {
            Log::warning('[loads.push] validation failed', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'errors' => $validator->errors()->toArray(),
                    'payload_has_file' => $request->hasFile('payload'),
                    'bolimage_has_file' => $request->hasFile('bolimage'),
                    'payload_file' => $request->hasFile('payload')
                        ? $this->fileSummary($request->file('payload'))
                        : null,
                    'bolimage_file' => $request->hasFile('bolimage')
                        ? $this->fileSummary($request->file('bolimage'))
                        : null,
                ]
            ));

            return response()->json([
                'ok' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
                'request_id' => $requestId,
            ], 422);
        }

        try {
            $dir = self::STORAGE_BASE_DIR . '/' . now()->format('Y-m-d');

            $payloadPath = null;
            $payloadOriginal = null;
            $payloadSize = null;
            $payloadJson = null;
            $payloadMode = null;
            $raw = null;

            /*
             * Read payload first so we can dedupe before writing files or inserting rows.
             */
            if ($request->hasFile('payload')) {
                $payloadMode = 'file';

                /** @var UploadedFile $file */
                $file = $request->file('payload');

                $payloadOriginal = $file->getClientOriginalName();
                $payloadSize = $file->getSize();
                $raw = $this->readUploadedFileContentsOrFail(
                    $file,
                    'payload',
                    $request,
                    $requestId
                );
            } else {
                $payloadMode = 'text';

                $rawInput = $request->input('payload');
                $raw = is_array($rawInput)
                    ? json_encode($rawInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) $rawInput;

                $payloadOriginal = 'payload.json';
                $payloadSize = strlen($raw);
            }

            $rawBeforeNormalize = $raw;
            $raw = $this->normalizeJsonInput($raw);

            $payloadJson = json_decode($raw, true);
            $jsonError = json_last_error();
            $jsonErrorMessage = json_last_error_msg();

            if ($payloadJson === null && $jsonError !== JSON_ERROR_NONE) {
                Log::error('[loads.push] invalid JSON payload', array_merge(
                    $this->baseLogContext($request, $requestId),
                    [
                        'payload_mode' => $payloadMode,
                        'payload_original' => $payloadOriginal,
                        'payload_size' => $payloadSize,
                        'json_error_code' => $jsonError,
                        'json_error_message' => $jsonErrorMessage,
                        'raw_prefix_hex_before_normalize' => bin2hex(substr((string) $rawBeforeNormalize, 0, 16)),
                        'raw_prefix_hex_after_normalize' => bin2hex(substr((string) $raw, 0, 16)),
                        'raw_preview_before_normalize' => $this->preview($rawBeforeNormalize),
                        'raw_preview_after_normalize' => $this->preview($raw),
                        'payload_file' => $request->hasFile('payload')
                            ? $this->fileSummary($request->file('payload'))
                            : null,
                    ]
                ));

                return response()->json([
                    'ok' => false,
                    'error' => 'Invalid JSON in payload',
                    'request_id' => $requestId,
                ], 422);
            }

            $jobname = is_array($payloadJson) ? ($payloadJson['jobname'] ?? null) : null;
            $canonicalPayloadJson = $this->canonicalizeJsonValue($payloadJson);

            $incomingImageHash = null;
            $incomingImageSize = null;

            if ($request->hasFile('bolimage')) {
                /** @var UploadedFile $img */
                $img = $request->file('bolimage');
                $incomingImageSize = $img->getSize();
                $incomingImageHash = $this->hashUploadedFileOrFail(
                    $img,
                    'bolimage',
                    $request,
                    $requestId
                );
            }

            $existing = $this->findDuplicateImport(
                $jobname,
                $canonicalPayloadJson,
                $incomingImageHash,
                $incomingImageSize
            );

            if ($existing) {
                Log::info('[loads.push] duplicate payload skipped', array_merge(
                    $this->baseLogContext($request, $requestId),
                    [
                        'existing_row_id' => $existing->id,
                        'jobname' => $jobname,
                        'payload_mode' => $payloadMode,
                        'incoming_image_size' => $incomingImageSize,
                        'incoming_image_hash' => $incomingImageHash,
                    ]
                ));

                return response()->json([
                    'ok' => true,
                    'id' => $existing->id,
                    'duplicate' => true,
                    'request_id' => $requestId,
                ], 200);
            }

            /*
             * Only store files after dedupe check passes.
             */
            if ($request->hasFile('payload')) {
                /** @var UploadedFile $file */
                $file = $request->file('payload');

                $name = Str::uuid()->toString() . '__' . ($payloadOriginal ?: 'payload.json');
                $payloadPath = $this->storeUploadedFileOrFail(
                    $file,
                    $dir,
                    $name,
                    'payload',
                    $request,
                    $requestId
                );

                $raw = $this->readStoredFileOrFail(
                    $payloadPath,
                    'payload',
                    $request,
                    $requestId
                );
            } else {
                $name = Str::uuid()->toString() . '__payload.json';
                $payloadPath = $dir . '/' . $name;

                $this->storeTextContentsOrFail(
                    $payloadPath,
                    $raw,
                    'payload',
                    $request,
                    $requestId
                );

                $raw = $this->readStoredFileOrFail(
                    $payloadPath,
                    'payload',
                    $request,
                    $requestId
                );
            }

            $imagePath = null;
            $imageOriginal = null;
            $imageSize = null;

            if ($request->hasFile('bolimage')) {
                /** @var UploadedFile $img */
                $img = $request->file('bolimage');

                $imageOriginal = $img->getClientOriginalName();
                $imageSize = $img->getSize();

                $imgName = Str::uuid()->toString() . '__' . ($imageOriginal ?: 'bolimage.jpg');
                $imagePath = $this->storeUploadedFileOrFail(
                    $img,
                    $dir,
                    $imgName,
                    'bolimage',
                    $request,
                    $requestId
                );

                $this->assertStoredFileReadableOrFail(
                    $imagePath,
                    'bolimage',
                    $request,
                    $requestId
                );
            }

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

            Log::info('[loads.push] load stored', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'payload_mode' => $payloadMode,
                    'row_id' => $row->id,
                    'jobname' => $jobname,
                    'payload_path' => $payloadPath,
                    'image_path' => $imagePath,
                    'incoming_image_size' => $incomingImageSize,
                    'incoming_image_hash' => $incomingImageHash,
                ]
            ));

            return response()->json([
                'ok' => true,
                'id' => $row->id,
                'duplicate' => false,
                'request_id' => $requestId,
            ], 201);
        } catch (Throwable $e) {
            Log::error('[loads.push] unhandled exception', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                ]
            ));

            return response()->json([
                'ok' => false,
                'error' => 'Server error while processing payload',
                'request_id' => $requestId,
            ], 500);
        }
    }

    private function findDuplicateImport(
        ?string $jobname,
        string $canonicalPayloadJson,
        ?string $incomingImageHash,
        ?int $incomingImageSize
    ): ?LoadImport {
        $query = LoadImport::query();

        if ($jobname !== null && $jobname !== '') {
            $query->where('jobname', $jobname);
        }

        if ($incomingImageHash === null) {
            $query->whereNull('image_path');
        } else {
            $query->whereNotNull('image_path');

            if ($incomingImageSize !== null) {
                $query->where('image_size', $incomingImageSize);
            }
        }

        $candidates = $query
            ->latest('id')
            ->limit(250)
            ->get();

        foreach ($candidates as $candidate) {
            $candidateCanonicalPayloadJson = $this->normalizeStoredPayloadForComparison($candidate->payload_json);

            if ($candidateCanonicalPayloadJson !== $canonicalPayloadJson) {
                continue;
            }

            if ($incomingImageHash === null) {
                if (empty($candidate->image_path)) {
                    return $candidate;
                }

                continue;
            }

            if (empty($candidate->image_path)) {
                continue;
            }

            $storedImageHash = $this->hashStoredFile($candidate->image_path);

            if ($storedImageHash !== null && hash_equals($storedImageHash, $incomingImageHash)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeStoredPayloadForComparison(mixed $value): string
    {
        if (is_string($value)) {
            $normalized = $this->normalizeJsonInput($value);
            $decoded = json_decode($normalized, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        return $this->canonicalizeJsonValue($value);
    }

    private function storeUploadedFileOrFail(
        UploadedFile $file,
        string $dir,
        string $name,
        string $field,
        Request $request,
        string $requestId
    ): string {
        $storedPath = $file->storeAs($dir, $name, self::STORAGE_DISK);

        if (!is_string($storedPath) || $storedPath === '') {
            Log::error('[loads.push] failed to store uploaded file', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'target_dir' => $dir,
                    'target_name' => $name,
                    'stored_path' => $storedPath,
                    'file' => $this->fileSummary($file),
                ]
            ));

            throw new RuntimeException("Failed to store {$field}");
        }

        if (!Storage::disk(self::STORAGE_DISK)->exists($storedPath)) {
            Log::error('[loads.push] uploaded file reported stored but does not exist on disk', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'stored_path' => $storedPath,
                    'target_dir' => $dir,
                    'target_name' => $name,
                    'file' => $this->fileSummary($file),
                ]
            ));

            throw new RuntimeException("Stored {$field} path does not exist on disk");
        }

        return $storedPath;
    }

    private function storeTextContentsOrFail(
        string $path,
        string $contents,
        string $field,
        Request $request,
        string $requestId
    ): void {
        $stored = Storage::disk(self::STORAGE_DISK)->put($path, $contents);

        if ($stored !== true) {
            Log::error('[loads.push] failed to store text contents', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'target_path' => $path,
                    'content_size' => strlen($contents),
                    'content_preview' => $this->preview($contents),
                ]
            ));

            throw new RuntimeException("Failed to store {$field}");
        }

        if (!Storage::disk(self::STORAGE_DISK)->exists($path)) {
            Log::error('[loads.push] text contents reported stored but do not exist on disk', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'target_path' => $path,
                    'content_size' => strlen($contents),
                ]
            ));

            throw new RuntimeException("Stored {$field} path does not exist on disk");
        }
    }

    private function readStoredFileOrFail(
        string $path,
        string $field,
        Request $request,
        string $requestId
    ): string {
        if (!Storage::disk(self::STORAGE_DISK)->exists($path)) {
            Log::error('[loads.push] cannot read back stored file because path does not exist', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                ]
            ));

            throw new RuntimeException("Stored {$field} file is missing");
        }

        try {
            $contents = Storage::disk(self::STORAGE_DISK)->get($path);
        } catch (Throwable $e) {
            Log::error('[loads.push] failed reading stored file back from disk', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]
            ));

            throw new RuntimeException("Unable to read stored {$field} file");
        }

        if (!is_string($contents)) {
            Log::error('[loads.push] read-back returned non-string contents', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                    'contents_type' => gettype($contents),
                ]
            ));

            throw new RuntimeException("Stored {$field} file is not readable");
        }

        return $contents;
    }

    private function assertStoredFileReadableOrFail(
        string $path,
        string $field,
        Request $request,
        string $requestId
    ): void {
        if (!Storage::disk(self::STORAGE_DISK)->exists($path)) {
            Log::error('[loads.push] cannot verify stored file because path does not exist', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                ]
            ));

            throw new RuntimeException("Stored {$field} file is missing");
        }

        try {
            $stream = Storage::disk(self::STORAGE_DISK)->readStream($path);
        } catch (Throwable $e) {
            Log::error('[loads.push] failed opening stored file for read-back verification', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]
            ));

            throw new RuntimeException("Unable to read stored {$field} file");
        }

        if (!is_resource($stream)) {
            Log::error('[loads.push] read-back verification returned invalid stream', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                ]
            ));

            throw new RuntimeException("Stored {$field} file is not readable");
        }

        $probe = fread($stream, 1);
        fclose($stream);

        if ($probe === false) {
            Log::error('[loads.push] stored file stream could not be read', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'path' => $path,
                ]
            ));

            throw new RuntimeException("Stored {$field} file is not readable");
        }
    }

    private function readUploadedFileContentsOrFail(
        UploadedFile $file,
        string $field,
        Request $request,
        string $requestId
    ): string {
        $path = $file->getRealPath();

        if (!is_string($path) || $path === '' || !is_file($path)) {
            Log::error('[loads.push] uploaded file has no readable temp path', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'file' => $this->fileSummary($file),
                ]
            ));

            throw new RuntimeException("Uploaded {$field} file is not readable");
        }

        $contents = @file_get_contents($path);

        if (!is_string($contents)) {
            Log::error('[loads.push] failed reading uploaded temp file', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'file' => $this->fileSummary($file),
                    'temp_path' => $path,
                ]
            ));

            throw new RuntimeException("Uploaded {$field} file could not be read");
        }

        return $contents;
    }

    private function hashUploadedFileOrFail(
        UploadedFile $file,
        string $field,
        Request $request,
        string $requestId
    ): string {
        $path = $file->getRealPath();

        if (!is_string($path) || $path === '' || !is_file($path)) {
            Log::error('[loads.push] uploaded file has no hashable temp path', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'file' => $this->fileSummary($file),
                ]
            ));

            throw new RuntimeException("Uploaded {$field} file is not readable for hashing");
        }

        $hash = @hash_file('sha256', $path);

        if (!is_string($hash) || $hash === '') {
            Log::error('[loads.push] failed hashing uploaded file', array_merge(
                $this->baseLogContext($request, $requestId),
                [
                    'field' => $field,
                    'file' => $this->fileSummary($file),
                    'temp_path' => $path,
                ]
            ));

            throw new RuntimeException("Uploaded {$field} file could not be hashed");
        }

        return $hash;
    }

    private function hashStoredFile(string $path): ?string
    {
        if (!Storage::disk(self::STORAGE_DISK)->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk(self::STORAGE_DISK)->path($path);

        if (!is_string($absolutePath) || $absolutePath === '' || !is_file($absolutePath)) {
            return null;
        }

        $hash = @hash_file('sha256', $absolutePath);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    private function normalizeJsonInput(?string $raw): string
    {
        $raw = (string) $raw;

        // Strip UTF-8 BOM if present.
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }

        return $raw;
    }

    private function canonicalizeJsonValue(mixed $value): string
    {
        $normalized = $this->canonicalizeValue($value);

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json)) {
            throw new RuntimeException('Failed to canonicalize payload JSON');
        }

        return $json;
    }

    private function canonicalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isAssoc($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeValue($item);
        }

        return $value;
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function baseLogContext(Request $request, string $requestId): array
    {
        return [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'expects_json' => $request->expectsJson(),
            'wants_json' => $request->wantsJson(),
            'has_payload_file' => $request->hasFile('payload'),
            'has_bolimage_file' => $request->hasFile('bolimage'),
        ];
    }

    private function fileSummary(?UploadedFile $file): ?array
    {
        if (!$file) {
            return null;
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'client_mime' => $file->getClientMimeType(),
            'server_mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'is_valid' => $file->isValid(),
            'error' => $file->getError(),
            'error_message' => $file->getErrorMessage(),
        ];
    }

    private function preview(?string $value, int $limit = 500): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(["\r", "\n"], ['\r', '\n'], $value);

        return Str::limit($value, $limit, '...');
    }
}
