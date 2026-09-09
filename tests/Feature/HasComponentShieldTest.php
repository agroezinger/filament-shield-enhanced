<?php

use Agroezinger\FilamentShieldEnhanced\Traits\HasComponentShield;
use Illuminate\Foundation\Auth\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ── Minimal fake Livewire-shaped component stub ────────────────────────────────

class FakeCommentComponent
{
    use HasComponentShield;

    public static function getShieldComponentPermissions(): array
    {
        return ['delete', 'edit'];
    }
}

// ── getShieldComponentPermissions ───────────────────────────────────────────────

describe('HasComponentShield — getShieldComponentPermissions()', function () {

    it('returns the declared permissions array', function () {
        expect(FakeCommentComponent::getShieldComponentPermissions())
            ->toBe(['delete', 'edit']);
    });

    it('returns empty array when not overridden', function () {
        $component = new class
        {
            use HasComponentShield;
        };

        expect($component::getShieldComponentPermissions())->toBeArray()->toBeEmpty();
    });

});

// ── canShield ────────────────────────────────────────────────────────────────

describe('HasComponentShield — canShield()', function () {

    it('returns false when no user is authenticated', function () {
        expect((new FakeCommentComponent)->canShield('delete'))->toBeFalse();
    });

    it('returns true for a user with the exact permission', function () {
        $perm = Permission::firstOrCreate(['name' => 'Component:Delete:FakeCommentComponent', 'guard_name' => 'web']);

        $user = new class extends User
        {
            use HasRoles;

            protected $table = 'users';

            protected string $guard_name = 'web';
        };
        $user->forceFill(['id' => 99]);
        $user->exists = true;

        $user->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);

        expect((new FakeCommentComponent)->canShield('delete'))->toBeTrue();
    });

    it('returns false for a user without the permission', function () {
        // "edit" has no matching permission row in this test at all — canShield()
        // must return false gracefully (via the Gate-routed can()), not throw
        // Spatie's PermissionDoesNotExist for an ungenerated/unmatched key.
        $user = new class extends User
        {
            public int $id = 100;

            protected $table = 'users';

            public function hasRole(string $role): bool
            {
                return false;
            }
        };

        $this->actingAs($user);

        expect((new FakeCommentComponent)->canShield('edit'))->toBeFalse();
    });

    it('returns false, does not throw, for a real Spatie user when the permission was never generated at all', function () {
        // Regression: canShield() must not prefer hasPermissionTo(), which
        // throws PermissionDoesNotExist for a name with zero matching rows —
        // even for a fully Spatie-equipped user. A declared action whose
        // permission hasn't been generated yet (e.g. shield:generate-enhanced-
        // components wasn't run after adding it) is "not granted", not a crash.
        $user = new class extends User
        {
            use HasRoles;

            protected $table = 'users';

            protected string $guard_name = 'web';
        };
        $user->forceFill(['id' => 106]);
        $user->exists = true;

        $this->actingAs($user);

        expect(fn () => (new FakeCommentComponent)->canShield('edit'))->not->toThrow(PermissionDoesNotExist::class);
        expect((new FakeCommentComponent)->canShield('edit'))->toBeFalse();
    });

    it('returns true for super_admin regardless of explicit permission', function () {
        $user = new class extends User
        {
            public int $id = 101;

            protected $table = 'users';

            public function hasRole(string $role): bool
            {
                return $role === 'super_admin';
            }
        };

        $this->actingAs($user);

        expect((new FakeCommentComponent)->canShield('delete'))->toBeTrue();
        expect((new FakeCommentComponent)->canShield('edit'))->toBeTrue();
    });

    it('respects a custom super_admin role name from config', function () {
        config(['filament-shield.super_admin.name' => 'platform_admin']);

        $user = new class extends User
        {
            public int $id = 102;

            protected $table = 'users';

            public function hasRole(string $role): bool
            {
                return $role === 'platform_admin';
            }
        };

        $this->actingAs($user);

        $result = (new FakeCommentComponent)->canShield('delete');

        config(['filament-shield.super_admin.name' => 'super_admin']); // reset

        expect($result)->toBeTrue();
    });

});

// ── authorizeShield ──────────────────────────────────────────────────────────

describe('HasComponentShield — authorizeShield()', function () {

    it('aborts with 403 when the user lacks the permission', function () {
        $user = new class extends User
        {
            public int $id = 103;

            protected $table = 'users';

            public function hasRole(string $role): bool
            {
                return false;
            }
        };

        $this->actingAs($user);

        (new FakeCommentComponent)->authorizeShield('delete');
    })->throws(HttpException::class);

    it('does not throw when the user has the permission', function () {
        $perm = Permission::firstOrCreate(['name' => 'Component:Delete:FakeCommentComponent', 'guard_name' => 'web']);

        $user = new class extends User
        {
            use HasRoles;

            protected $table = 'users';

            protected string $guard_name = 'web';
        };
        $user->forceFill(['id' => 104]);
        $user->exists = true;

        $user->givePermissionTo($perm);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);

        (new FakeCommentComponent)->authorizeShield('delete');
    })->throwsNoExceptions();

});

// ── getShieldPermissions ─────────────────────────────────────────────────────

describe('HasComponentShield — getShieldPermissions()', function () {

    it('returns a map of action => bool for all declared permissions', function () {
        $user = new class extends User
        {
            public int $id = 105;

            protected $table = 'users';

            public function hasRole(string $role): bool
            {
                return $role === 'super_admin';
            }
        };

        $this->actingAs($user);

        $map = (new FakeCommentComponent)->getShieldPermissions();

        expect($map)
            ->toBeArray()
            ->toHaveKeys(['delete', 'edit'])
            ->and($map['delete'])->toBeTrue()
            ->and($map['edit'])->toBeTrue();
    });

});
