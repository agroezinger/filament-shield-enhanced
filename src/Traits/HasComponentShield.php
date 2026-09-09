<?php

namespace Agroezinger\FilamentShieldEnhanced\Traits;

use Agroezinger\FilamentShieldEnhanced\Support\ComponentPermissionKeyBuilder;
use Illuminate\Support\Facades\Auth;

/**
 * HasComponentShield
 *
 * Fine-grained permissions for arbitrary Livewire components — the third
 * category alongside HasPageShield (Pages) and HasResourceShield (Resources).
 * Components aren't registered with any Filament panel, so unlike Pages they
 * declare permissions but have no navigation/canAccess concept of their own.
 *
 * Usage:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Traits\HasComponentShield;
 *
 *   class CommentComponent extends Component
 *   {
 *       use HasComponentShield;
 *
 *       public static function getShieldComponentPermissions(): array
 *       {
 *           return ['delete', 'edit'];
 *       }
 *
 *       public function delete(Comment $comment): void
 *       {
 *           $this->authorizeShield('delete'); // aborts 403 if not permitted
 *           // …
 *       }
 *   }
 *
 * Permission keys follow the same three-part convention as pages:
 *   {Prefix}{sep}{Action}{sep}{Subject} — e.g. "Component:Delete:CommentComponent"
 *
 * Run `php artisan shield:generate-enhanced-components` to seed permissions
 * for every component found by ComponentDiscovery, and use
 * EnhancedComponentPermissionsForm in a published RoleResource to manage them.
 */
trait HasComponentShield
{
    /**
     * Declare every action that can be independently granted on this component.
     * No default — unlike pages there is no universally meaningful action name
     * for an arbitrary component, so an empty declaration grants nothing.
     *
     *   public static function getShieldComponentPermissions(): array
     *   {
     *       return ['delete', 'edit'];
     *   }
     */
    public static function getShieldComponentPermissions(): array
    {
        return [];
    }

    /**
     * Check a single named action on this component.
     *
     *   if ($this->canShield('delete')) { ... }
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

        // $user->can() (Gate-routed), not hasPermissionTo(): the latter throws
        // PermissionDoesNotExist for a key with no matching row at all, instead
        // of returning false — matches vanilla filament-shield's own behaviour.
        return $user->can($key);
    }

    /**
     * Abort with a 403 if the user does not hold the given permission.
     * Useful as a guard at the start of Livewire action methods.
     *
     *   public function delete(): void
     *   {
     *       $this->authorizeShield('delete');
     *       // …
     *   }
     */
    public function authorizeShield(string $action): void
    {
        abort_unless($this->canShield($action), 403);
    }

    /**
     * Returns a map of action => bool for all permissions declared on this
     * component. Use this for top-down injection into child Livewire
     * components via HasInjectedShieldPermissions:
     *
     *   @livewire('child-component', ['permissions' => $this->getShieldPermissions()])
     */
    public function getShieldPermissions(): array
    {
        $map = [];

        foreach (static::getShieldComponentPermissions() as $k => $v) {
            $action = is_int($k) ? $v : $k;
            $map[$action] = $this->canShield($action);
        }

        return $map;
    }

    protected static function resolvePermissionKeyForAction(string $action): string
    {
        return ComponentPermissionKeyBuilder::build(
            entity: static::class,
            affix: $action,
            subject: class_basename(static::class),
            case: config('filament-shield.permissions.case', 'pascal'),
            separator: config('filament-shield.permissions.separator', ':'),
        );
    }
}
