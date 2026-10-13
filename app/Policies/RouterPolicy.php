<?php

namespace App\Policies;

use App\Models\Router;
use App\Models\User;

class RouterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view routers');
    }

    public function view(User $user, Router $router): bool
    {
        return $user->hasPermission('view routers');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create routers');
    }

    public function update(User $user, Router $router): bool
    {
        return $user->hasPermission('edit routers');
    }

    public function delete(User $user, Router $router): bool
    {
        return $user->hasPermission('delete routers');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete routers');
    }
}
