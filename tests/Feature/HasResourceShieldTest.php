<?php

use Agroezinger\FilamentShieldEnhanced\Support\ResourcePermissionKeyBuilder;
use Agroezinger\FilamentShieldEnhanced\Traits\HasResourceShield;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// ── Minimal fake Resource stub ────────────────────────────────────────────────

class FakeModel {}

class FakeResource
{
    use HasResourceShield;

    public static function getModel(): string
    {
        return FakeModel::class;
    }

    public static function getShieldResourcePermissions(): array
    {
        return [
            'Export'          => 'Export records',
            'ViewContactInfo' => 'View contact info',
        ];
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeResourceUser(array $permissions = []): \Illuminate\Foundation\Auth\User
{
    $user = new class extends \Illuminate\Foundation\Auth\User {
        public int $id = 1;
        protected $table = 'users';

        public function hasRole(string $role): bool { return false; }
    };

    if (! empty($permissions)) {
        foreach ($permissions as $permission) {
            $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($perm);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    return $user;
}

// ── resolveShieldPermissionKey ────────────────────────────────────────────────

describe('HasResourceShield — resolveShieldPermissionKey()', function () {

    it('builds the correct key from action and model basename', function () {
        $key = FakeResource::resolveShieldPermissionKey('Export');

        expect($key)->toBe('Export:FakeModel');
    });

    it('applies pascal case by default', function () {
        $key = FakeResource::resolveShieldPermissionKey('viewContactInfo');

        expect($key)->toBe('ViewContactInfo:FakeModel');
    });

    it('uses the separator from config', function () {
        config(['filament-shield.permissions.separator' => '|']);

        $key = FakeResource::resolveShieldPermissionKey('Export');

        config(['filament-shield.permissions.separator' => ':']); // reset

        expect($key)->toBe('Export|FakeModel');
    });

});

// ── getShieldResourcePermissions ──────────────────────────────────────────────

describe('HasResourceShield — getShieldResourcePermissions()', function () {

    it('returns the declared permissions array', function () {
        $perms = FakeResource::getShieldResourcePermissions();

        expect($perms)
            ->toBeArray()
            ->toHaveKey('Export')
            ->toHaveKey('ViewContactInfo');
    });

    it('returns empty array when not overridden', function () {
        $resource = new class {
            use HasResourceShield;
            public static function getModel(): string { return FakeModel::class; }
        };

        expect($resource::getShieldResourcePermissions())->toBeArray()->toBeEmpty();
    });

});

// ── getShieldPermissions ──────────────────────────────────────────────────────

describe('HasResourceShield — getShieldPermissions()', function () {

    it('returns a map of action => bool for all declared permissions', function () {
        $map = FakeResource::getShieldPermissions();

        expect($map)
            ->toBeArray()
            ->toHaveKeys(['Export', 'ViewContactInfo']);
    });

    it('all values are boolean', function () {
        $map = FakeResource::getShieldPermissions();

        foreach ($map as $action => $allowed) {
            expect($allowed)->toBeBool();
        }
    });

});

// ── canShield ─────────────────────────────────────────────────────────────────

describe('HasResourceShield — canShield()', function () {

    it('returns false when no user is authenticated', function () {
        expect(FakeResource::canShield('Export'))->toBeFalse();
    });

    it('returns true for a user with the exact permission', function () {
        $perm = Permission::firstOrCreate(['name' => 'Export:FakeModel', 'guard_name' => 'web']);

        $user = new class extends \Illuminate\Foundation\Auth\User {
            use \Spatie\Permission\Traits\HasRoles;

            protected $table = 'users';
            protected string $guard_name = 'web';
        };
        // forceFill sets id in $attributes so getKey() returns it (PHP properties bypass Eloquent)
        // exists = true prevents Spatie from deferring attach() to a never-fired saved() callback
        $user->forceFill(['id' => 99]);
        $user->exists = true;

        $user->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);

        expect(FakeResource::canShield('Export'))->toBeTrue();
    });

    it('returns false for a user without the permission', function () {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            public int $id = 100;
            protected $table = 'users';
            public function hasRole(string $role): bool { return false; }
        };

        $this->actingAs($user);

        expect(FakeResource::canShield('ViewContactInfo'))->toBeFalse();
    });

    it('returns true for super_admin regardless of explicit permission', function () {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            public int $id = 101;
            protected $table = 'users';
            public function hasRole(string $role): bool { return $role === 'super_admin'; }
        };

        $this->actingAs($user);

        // No permission assigned — super_admin bypass should grant access
        expect(FakeResource::canShield('Export'))->toBeTrue();
        expect(FakeResource::canShield('ViewContactInfo'))->toBeTrue();
    });

    it('respects a custom super_admin role name from config', function () {
        config(['filament-shield.super_admin.name' => 'platform_admin']);

        $user = new class extends \Illuminate\Foundation\Auth\User {
            public int $id = 102;
            protected $table = 'users';
            public function hasRole(string $role): bool { return $role === 'platform_admin'; }
        };

        $this->actingAs($user);

        expect(FakeResource::canShield('Export'))->toBeTrue();

        config(['filament-shield.super_admin.name' => 'super_admin']); // reset
    });

});
