<?php

namespace Agroezinger\FilamentShieldEnhanced\Tests;

use Agroezinger\FilamentShieldEnhanced\FilamentShieldEnhancedServiceProvider;
use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            FilamentShieldServiceProvider::class,
            FilamentShieldEnhancedServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver'   => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model'  => \Illuminate\Foundation\Auth\User::class,
        ]);

        $app['config']->set('permission.table_names', [
            'roles'                 => 'roles',
            'permissions'           => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles'       => 'model_has_roles',
            'role_has_permissions'  => 'role_has_permissions',
        ]);

        $app['config']->set('filament-shield.permissions', [
            'separator' => ':',
            'case'      => 'pascal',
            'generate'  => true,
        ]);

        $app['config']->set('filament-shield.super_admin', [
            'enabled'        => true,
            'name'           => 'super_admin',
            'define_via_gate' => true,
            'intercept_gate' => 'before',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Spatie ships migrations as .php.stub files — loadMigrationsFrom() skips them.
        // Include the stub directly (it returns an anonymous Migration instance) and run it.
        $stub = __DIR__ . '/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
        $migration = include $stub;
        $migration->up();
    }
}
