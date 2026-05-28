<?php

namespace Agroezinger\FilamentShieldEnhanced\Traits;

use Agroezinger\FilamentShieldEnhanced\Support\PagePermissionKeyBuilder;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\Facades\Auth;

/**
 * Enhanced HasPageShield trait.
 *
 * Drop this onto any Filament Page instead of (or alongside) the original
 * BezhanSalleh\FilamentShield\Traits\HasPageShield trait.
 *
 * New capabilities over the original:
 *  – getShieldPagePermissions() — define multiple permissions per page.
 *  – canShield(string $action)  — type-safe per-action check in PHP & Blade.
 *  – getShieldPermissions()     — bool-map for injection into child Livewire
 *    components via HasInjectedShieldPermissions.
 *
 * When the page does NOT override getShieldPagePermissions() this trait falls
 * back to a single 'view' permission — identical to the original trait.
 */
trait HasPageShield
{
    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    public function bootedHasPageShield(): void
    {
        if (! static::canAccess()) {
            $this->beforeShieldRedirects();
            redirect()->to(static::getShieldRedirectUrl())->send();

            return;
        }
    }

    // -------------------------------------------------------------------------
    // Fine-grained permission declaration (override in your Page class)
    // -------------------------------------------------------------------------

    /**
     * Declare every action that can be independently granted on this page.
     * The 'view' action controls whether the user can navigate to the page.
     *
     *   public static function getShieldPagePermissions(): array
     *   {
     *       return ['view', 'editGlobalSettings', 'exportData'];
     *   }
     */
    public static function getShieldPagePermissions(): array
    {
        return ['view'];
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    /**
     * The page is accessible when the user holds *any* of the declared
     * permissions (partial access: can navigate → can view, but not edit).
     */
    public static function canAccess(): bool
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

        $keys = static::resolveAllPermissionKeys();

        if (empty($keys)) {
            return false;
        }

        if (method_exists($user, 'hasAnyPermission')) {
            return $user->hasAnyPermission($keys);
        }

        foreach ($keys as $key) {
            if ($user->can($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check a single named action on this page.
     *
     *   if ($this->canShield('editGlobalSettings')) { ... }
     *
     * Also works in Blade:
     *   @if($this->canShield('editGlobalSettings')) ... @endif
     */
    public function canShield(string $action): bool
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

        $key = static::resolvePermissionKeyForAction($action);

        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($key);
        }

        return $user->can($key);
    }

    /**
     * Returns a map of action => bool for all permissions declared on this page.
     * Use this for top-down injection into child Livewire components:
     *
     *   @livewire('my-component', ['permissions' => $this->getShieldPermissions()])
     */
    public function getShieldPermissions(): array
    {
        $map = [];

        foreach (static::getShieldPagePermissions() as $action) {
            $map[$action] = $this->canShield($action);
        }

        return $map;
    }

    // -------------------------------------------------------------------------
    // Internal key resolution
    // -------------------------------------------------------------------------

    /**
     * Resolves the permission key for every declared action.
     *
     * We call PagePermissionKeyBuilder directly (bypassing the Facade's
     * registered callback) because we are resolving permissions *for* this
     * page, not *through* the generic generator.
     *
     * @return list<string>
     */
    protected static function resolveAllPermissionKeys(): array
    {
        $subject   = class_basename(static::class);
        $separator = config('filament-shield.permissions.separator', ':');
        $case      = config('filament-shield.permissions.case', 'pascal');

        return array_map(
            fn (string $action) => PagePermissionKeyBuilder::build(
                entity: static::class,
                affix: $action,
                subject: $subject,
                case: $case,
                separator: $separator,
            ),
            static::getShieldPagePermissions(),
        );
    }

    protected static function resolvePermissionKeyForAction(string $action): string
    {
        return PagePermissionKeyBuilder::build(
            entity: static::class,
            affix: $action,
            subject: class_basename(static::class),
            case: config('filament-shield.permissions.case', 'pascal'),
            separator: config('filament-shield.permissions.separator', ':'),
        );
    }

    // -------------------------------------------------------------------------
    // Redirect helpers (mirror the original trait)
    // -------------------------------------------------------------------------

    /** Hook: called before redirecting away. Override to run cleanup logic. */
    protected function beforeShieldRedirects(): void {}

    protected static function getShieldRedirectUrl(): string
    {
        return config('filament.path', '/admin');
    }
}
