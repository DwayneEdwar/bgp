<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage users');
    }

    public function view(User $user, User $record): bool
    {
        return $user->hasPermission('manage users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage users');
    }

    public function viewAuditLogs(User $user): bool
    {
        return $user->hasPermission('view audit logs');
    }

    public function update(User $user, User $record): bool
    {
        if (! $user->hasPermission('manage users')) {
            return false;
        }

        return ! $record->roles()->where('name', 'Super Admin')->exists() || $user->hasPermission('manage roles');
    }

    public function delete(User $user, User $record): bool
    {
        return $user->hasPermission('manage users')
            && $record->isNot($user)
            && (! $record->roles()->where('name', 'Super Admin')->exists() || $user->hasPermission('manage roles'));
    }
}
