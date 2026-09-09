<?php

use Agroezinger\FilamentShieldEnhanced\Traits\HasComponentShield;
use Illuminate\Foundation\Auth\User;
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
        // Deliberately not using HasRoles here: Spatie's real hasPermissionTo()
        // throws PermissionDoesNotExist for a name with no matching row at all,
        // which "edit" has none of in this test. Without the trait, canShield()
        // falls through to the generic Gate-based can() check, which returns
        // false gracefully for an unrecognised ability — matching how a real
        // app behaves once shield:generate-enhanced-components has seeded the
        // permission but no role holds it yet (see the positive test above,
        // which does create the row).
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
