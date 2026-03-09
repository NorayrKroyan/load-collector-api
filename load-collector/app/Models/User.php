<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /**
     * Traits in use:
     * - HasApiTokens: required for Laravel Sanctum personal access token support
     * - HasFactory: standard Laravel model factory support
     * - Notifiable: standard notification support
     *
     * `HasApiTokens` is the critical piece for this API because the custom
     * `app:issue-token` command calls `$user->createToken(...)`.
     */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass-assignable user columns.
     *
     * These are sufficient for the custom token-issuing command, which may create
     * an API user automatically if the supplied email does not already exist.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Sensitive fields hidden when the model is serialized.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
}
