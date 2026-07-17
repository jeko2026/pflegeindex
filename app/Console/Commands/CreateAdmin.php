<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class CreateAdmin extends Command
{
    protected $signature = 'pflegeindex:create-admin
                            {email=info@pflegeindex.com : Administrator email address}
                            {--password= : Password; entered securely when omitted}';

    protected $description = 'Create or update a PflegeIndex administrator';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Please provide a valid email address.');
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException('The password must contain at least 12 characters.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'PflegeIndex Admin',
                'password' => $password,
                'is_admin' => true,
            ],
        );

        $this->components->info("Administrator ready: {$email}");

        return self::SUCCESS;
    }
}
