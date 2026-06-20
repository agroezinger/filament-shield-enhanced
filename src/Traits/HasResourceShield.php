<?php

namespace Agroezinger\FilamentShieldEnhanced\Traits;

use Agroezinger\FilamentShieldEnhanced\Support\ResourcePermissionKeyBuilder;
use Illuminate\Support\Facades\Auth;

/**
 * Add to any Filament Resource class to declare fine-grained custom permissions
 * beyond the standard CRUD policy methods.
 *
 * Usage:
 *
 *   class MemberResource extends Resource
 *   {
 *       use HasResourceShield;
 *
 *       public static function getShieldResourcePermissions(): array
 *       {
 *           return [
 *               'Export'          => 'Mitglieder exportieren',
 *               'ViewContactInfo' => 'Kontaktdaten einsehen',
 *           ];
 *       }
 *   }
 *
 * Checking a permission:
 *   MemberResource::canShield('ViewContactInfo')  // → bool
 *   MemberResource::getShieldPermissions()        // → ['Export' => true, 'ViewContactInfo' => false]
 */
trait HasResourceShield
{
    // -------------------------------------------------------------------------
    // Declaration (override in your Resource class)
    // -------------------------------------------------------------------------

    /**
     * Declare every custom action that can be independently granted on this
     * resource. Keys are action names, values are human-readable labels.
     *
     *   public static function getShieldResourcePermissions(): array
     *   {
     *       return [
     *           'Export'          => 'Mitglieder exportieren',
     *           'ViewContactInfo' => 'Kontaktdaten einsehen (E-Mail, Telefon, Adresse)',
     *       ];
     *   }
     */
    public static function getShieldResourcePermissions(): array
    {
        return [];
    }

    // -------------------------------------------------------------------------
    // Permission checks
    // -------------------------------------------------------------------------

    /**
     * Check a single named action on this resource.
     *
     *   if (MemberResource::canShield('ViewContactInfo')) { … }
     */
    public static function canShield(string $action): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Super-admin bypass.
        if (method_exists($user, 'hasRole')
            && $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'))
        ) {
            return true;
        }

        $key = static::resolveShieldPermissionKey($action);

        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($key);
        }

        return $user->can($key);
    }

    /**
     * Returns a map of action => bool for all permissions declared on this resource.
     *
     *   MemberResource::getShieldPermissions()
     *   // → ['Export' => true, 'ViewContactInfo' => false, …]
     */
    public static function getShieldPermissions(): array
    {
        $map = [];

        foreach (array_keys(static::getShieldResourcePermissions()) as $action) {
            $map[$action] = static::canShield($action);
        }

        return $map;
    }

    // -------------------------------------------------------------------------
    // Internal key resolution
    // -------------------------------------------------------------------------

    public static function resolveShieldPermissionKey(string $action): string
    {
        return ResourcePermissionKeyBuilder::build(
            entity: static::class,
            affix: $action,
            subject: class_basename(static::getModel()),
            case: config('filament-shield.permissions.case', 'pascal'),
            separator: config('filament-shield.permissions.separator', ':'),
        );
    }
}
