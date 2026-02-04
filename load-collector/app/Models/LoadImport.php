<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadImport extends Model
{
    protected $table = 'loadimports';

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

    protected $casts = [
        'payload_json' => 'array',
    ];
}
