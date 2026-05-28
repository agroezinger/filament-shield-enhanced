<?php

namespace Agroezinger\FilamentShieldEnhanced\Concerns;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;

/**
 * Shared static helpers consumed by HasPageShield and any other class that
 * needs to resolve page-permission keys from the addon's three-part format.
 *
 * @internal  Not part of the public addon API — may change between minor
 *            versions. Depend on HasPageShield instead.
 */
trait InteractsWithShieldPagePermissions
{
    /**
     * Returns every resolved permission key for the calling Page class.
     *
     * @return list<string>
     */
    public static function getResolvedShieldPermissionKeys(): array
    {
        if (! method_exists(static::class, 'getShieldPagePermissions')) {
            return [];
        }

        $subject   = class_basename(static::class);
        $separator = config('filament-shield.permissions.separator', ':');
        $case      = config('filament-shield.permissions.case', 'pascal');

        return array_values(array_map(
            fn (string $action) => FilamentShield::buildPermissionKey(
                entity: static::class,
                affix: $action,
                subject: $subject,
                case: $case,
                separator: $separator,
            ),
            static::getShieldPagePermissions(),
        ));
    }
}
