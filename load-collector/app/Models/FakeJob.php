<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FakeJob extends Model
{
    protected $table = 'fake_jobs';

    protected $fillable = ['jobname', 'signature'];

    protected $casts = [
        'signature' => 'array',
    ];
}
