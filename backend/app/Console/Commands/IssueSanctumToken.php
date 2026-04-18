<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueSanctumToken extends Command
{
    /**
     * Command signature.
     *
     * This command is intended to be the operational entry point for creating or
     * refreshing the Bearer token used by the remote Load Collection client.
     *
     * Example usage:
     *
     * php artisan app:issue-token \
     *   --email=api@example.com \
     *   --name=load-collector-prod \
     *   --revoke-old
     *
     * Behavior summary:
     * - finds the user by email
     * - creates the user if it does not already exist
     * - optionally deletes all old Sanctum tokens for that user
     * - creates one new personal access token
     * - prints the plain text token once so it can be copied into the remote system
     *
     * Important:
     * Sanctum only returns the plain text token at creation time. After that, the
     * hashed token remains in the database but the full plain text value cannot be
     * retrieved again. That is why this command prints it immediately.
     */
    protected $signature = 'app:issue-token
        {--email=api@example.com : Email address for the API user account}
        {--name=load-collector : Human-readable token name shown in Sanctum token records}
        {--revoke-old : Delete all existing tokens for this user before issuing a new one}
        {--password=change-me : Password to assign if the user account must be created}';

    /**
     * Command description shown in `php artisan list`.
     */
    protected $description = 'Create or find an API user and issue a Sanctum personal access token';

    /**
     * Execute the command.
     *
     * Operational flow:
     * 1. Read CLI options.
     * 2. Validate required options (`email`, `name`).
     * 3. Find existing user by email.
     * 4. Create the user if it does not exist.
     * 5. Optionally revoke all prior tokens for a clean rotation.
     * 6. Issue a new Sanctum token.
     * 7. Print the token so the operator can copy it into the external collector.
     *
     * Notes for future maintainers:
     * - This command assumes the `users` table exists and the `User` model uses
     *   `Laravel\Sanctum\HasApiTokens`.
     * - Deleting old tokens is often desirable in production so only one valid
     *   collector token exists at a time.
     * - The user password is largely irrelevant to token-based machine-to-machine
     *   usage, but a password is still required when creating a normal Laravel user row.
     */
    public function handle(): int
    {
        // Normalize all CLI input early so the rest of the command works with clean values.
        $email = trim((string) $this->option('email'));
        $tokenName = trim((string) $this->option('name'));
        $revokeOld = (bool) $this->option('revoke-old');
        $password = (string) $this->option('password');

        // Guard clause: the command should never proceed with a blank email.
        if ($email === '') {
            $this->error('Email cannot be empty.');
            return self::FAILURE;
        }

        // Guard clause: token names are useful for operational visibility and should not be blank.
        if ($tokenName === '') {
            $this->error('Token name cannot be empty.');
            return self::FAILURE;
        }

        // Look for an existing API user by email.
        // The email is the stable operational identifier for token ownership.
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Create a dedicated API user if one does not already exist.
            // Password is hashed immediately using bcrypt before persistence.
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
            // Token rotation path.
            // This deletes every existing token for the user, which is helpful when:
            // - replacing a compromised token
            // - simplifying production support
            // - ensuring only one remote collector is authorized
            $deleted = $user->tokens()->delete();
            $this->warn("Revoked old tokens: {$deleted}");
        }

        // Issue the new Sanctum personal access token.
        // The returned string is the ONLY moment where the plain text token can be seen.
        $plainTextToken = $user->createToken($tokenName)->plainTextToken;

        // Print the token clearly for operators.
        // This output is intentionally simple so it can be copied directly into env files,
        // deployment notes, or third-party remote scripts.
        $this->line('');
        $this->info('NEW TOKEN (copy now):');
        $this->line($plainTextToken);
        $this->line('');

        return self::SUCCESS;
    }
}
