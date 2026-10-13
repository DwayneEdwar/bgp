<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view routers', 'create routers', 'edit routers', 'delete routers',
            'view monitoring', 'view routing', 'view BGP', 'manage BGP',
            'view configuration', 'manage configuration', 'view alerts', 'manage alerts',
            'view logs', 'view audit logs', 'manage users', 'manage roles', 'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }

        foreach (['Super Admin', 'Network Admin', 'NOC Operator', 'Read Only'] as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName]);
        }

        Role::query()->where('name', 'Super Admin')->firstOrFail()->permissions()->sync(
            Permission::query()->pluck('id')->all(),
        );

        Role::query()->where('name', 'Network Admin')->firstOrFail()->permissions()->sync(
            Permission::query()->whereNotIn('name', ['manage users', 'manage roles'])->pluck('id')->all(),
        );

        Role::query()->where('name', 'NOC Operator')->firstOrFail()->permissions()->sync(
            Permission::query()->whereIn('name', ['view routers', 'view monitoring', 'view routing', 'view BGP', 'view alerts', 'view logs'])->pluck('id')->all(),
        );

        Role::query()->where('name', 'Read Only')->firstOrFail()->permissions()->sync(
            Permission::query()->whereIn('name', ['view routers', 'view monitoring', 'view routing', 'view BGP', 'view configuration', 'view alerts', 'view logs', 'view audit logs'])->pluck('id')->all(),
        );
    }
}
