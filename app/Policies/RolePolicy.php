<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('manage roles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('manage roles') && $role->name !== 'Super Admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('manage roles') && $role->name !== 'Super Admin';
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('manage roles');
    }
}
