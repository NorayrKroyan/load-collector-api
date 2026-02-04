<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueSanctumToken extends Command
{
    /**
     * Usage:
     *  php artisan app:issue-token --email=api@example.com --name=load-collector-prod --revoke-old
     */
    protected $signature = 'app:issue-token
        {--email=api@example.com : Email of the API user}
        {--name=load-collector : Token name}
        {--revoke-old : Revoke (delete) all existing tokens for this user before issuing a new one}
        {--password=change-me : Password to set if user must be created}';

    protected $description = 'Create/find an API user and issue a Sanctum personal access token';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        $tokenName = trim((string) $this->option('name'));
        $revokeOld = (bool) $this->option('revoke-old');
        $password = (string) $this->option('password');

        if ($email === '') {
            $this->error('Email cannot be empty.');
            return self::FAILURE;
        }

        if ($tokenName === '') {
            $this->error('Token name cannot be empty.');
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name' => 'API',
                'email' => $email,
                'password' => bcrypt($password),
            ]);

            $this->info("Created user: {$user->email} (id={$user->id})");
        } else {
            $this->info("Using existing user: {$user->email} (id={$user->id})");
        }

        if ($revokeOld) {
            $deleted = $user->tokens()->delete();
            $this->warn("Revoked old tokens: {$deleted}");
        }

        $plainTextToken = $user->createToken($tokenName)->plainTextToken;

        // Print token (copy it once; you can't retrieve it later)
        $this->line('');
        $this->info('NEW TOKEN (copy now):');
        $this->line($plainTextToken);
        $this->line('');

        return self::SUCCESS;
    }
}
