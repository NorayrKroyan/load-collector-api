<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadImport extends Model
{
    /**
     * Table used by the ingestion endpoint.
     *
     * Each row represents one accepted upload event from the remote collector.
     * A row may include:
     * - an extracted job name
     * - the stored JSON payload file path and metadata
     * - the stored BOL image path and metadata
     * - a decoded JSON snapshot stored directly in the DB for quick inspection
     */
    protected $table = 'loadimports';

    /**
     * Mass-assignable fields used by LoadsController::push().
     *
     * These are the exact columns the controller writes when persisting an upload.
     */
    protected $fillable = [
        'jobname',

        'payload_path',
        'payload_original',
        'payload_size',

        'image_path',
        'image_original',
        'image_size',

        'payload_json',
    ];

    /**
     * Cast the JSON column into a PHP array automatically.
     *
     * This is useful for:
     * - inspecting rows in tinker/controllers/jobs
     * - avoiding manual json_decode calls after fetching a model
     */
    protected $casts = [
        'payload_json' => 'array',
    ];
}
