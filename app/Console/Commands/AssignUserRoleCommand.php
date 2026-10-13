<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRoleCommand extends Command
{
    protected $signature = 'users:assign-role {email : Existing user email} {role : Existing role name}';

    protected $description = 'Explicitly assign an existing role to an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        $role = Role::query()->where('name', $this->argument('role'))->first();

        if ($user === null || $role === null) {
            $this->error('The user or role could not be found.');

            return self::FAILURE;
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);
        $this->info('Role assigned to the specified user.');

        return self::SUCCESS;
    }
}
