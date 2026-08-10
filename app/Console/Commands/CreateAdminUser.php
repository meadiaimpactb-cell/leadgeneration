<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an admin account interactively.
 *
 * Deliberately not a seeder: a seeded admin means a known password reaching
 * production. §19 requires handing over a full-permission account, and it must
 * be one whose credentials only the client ever sees.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'amad:create-admin
                            {--name= : Display name}
                            {--email= : Login email}
                            {--role=super-admin : One of the roles in §9.1}';

    protected $description = 'Create an admin-panel user and assign a role';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $role = (string) $this->option('role');

        if (! in_array($role, User::ROLES, true)) {
            $this->error("Unknown role [{$role}]. Available: ".implode(', ', User::ROLES));

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("A user with email [{$email}] already exists.");

            return self::FAILURE;
        }

        $secret = password('Password', required: true);

        $validator = Validator::make(
            ['email' => $email, 'password' => $secret],
            [
                'email' => ['required', 'email'],
                'password' => ['required', Password::min(12)->letters()->numbers()->symbols()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($secret),
            'is_active' => true,
        ]);

        $user->assignRole($role);

        $this->info("Created [{$email}] with role [{$role}].");

        if ($role === User::ROLE_SUPER_ADMIN) {
            $this->warn('2FA is mandatory for super-admin (§9.2) — enrol on first sign-in.');
        }

        return self::SUCCESS;
    }
}
