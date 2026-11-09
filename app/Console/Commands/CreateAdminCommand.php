<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create 
                            {email? : The admin email address} 
                            {--password= : The admin password}
                            {--name= : The admin name}';

    protected $description = 'Create or reset an administrator account';

    public function handle(): int
    {
        $email = $this->argument('email') ?: 'admin@pastq.test';
        $name = $this->option('name') ?: 'Platform Admin';
        $password = $this->option('password') ?: 'Admin@12345';

        $parts = explode(' ', trim($name), 2);
        $firstName = $parts[0] ?? 'Platform';
        $lastName = $parts[1] ?? 'Admin';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => $password, // automatically hashed by User model cast
                'role' => 'ADMIN',
                'status' => 'ACTIVE',
            ]
        );

        $this->info("✓ Administrator account ready for {$user->email} (ID: {$user->id})");
        return Command::SUCCESS;
    }
}
