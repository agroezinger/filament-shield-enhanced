<?php

use BezhanSalleh\FilamentShield\Facades\FilamentShield;

// ── Regression: buildPermissionKeyUsing() hook must tolerate affix: null ──────
//
// filament-shield resolves *custom* permission keys (config('filament-shield.
// custom_permissions')) via resolveCustomPermissionKey(), which invokes the
// registered buildPermissionKeyUsing() closure with entity: 'custom' and
// affix: null (see HasEntityTransformers::resolveCustomPermissionKey()). The
// addon's own hook (registered unconditionally in packageBooted(), regardless
// of whether any Page uses the enhanced trait) originally type-hinted the
// closure's $affix parameter as non-nullable `string`, causing a TypeError
// the moment any app declared a non-empty `custom_permissions` config entry.

describe('buildPermissionKeyUsing() hook — custom permissions', function () {

    it('does not throw when custom_permissions is non-empty', function () {
        config(['filament-shield.custom_permissions' => ['Export:Report']]);

        expect(fn () => FilamentShield::getCustomPermissions())->not->toThrow(TypeError::class);
    });

    it('resolves custom permission keys unchanged (no page-style prefix applied)', function () {
        config(['filament-shield.custom_permissions' => ['Export:Report']]);

        $permissions = FilamentShield::getCustomPermissions();

        expect($permissions)->toHaveKey('Export:Report');
    });

});
