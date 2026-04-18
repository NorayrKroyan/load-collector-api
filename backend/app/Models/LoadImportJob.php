<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadImportJob extends Model
{
    /**
     * Canonical source table for import jobs.
     */
    protected $table = 'loadimport_jobs';

    /**
     * Mass assignable columns.
     */
    protected $fillable = [
        'jobname',
        'signature',
    ];

    /**
     * Cast JSON signature into array automatically.
     */
    protected $casts = [
        'signature' => 'array',
    ];
}