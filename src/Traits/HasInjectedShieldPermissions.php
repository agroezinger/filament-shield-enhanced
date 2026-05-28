<?php

namespace Agroezinger\FilamentShieldEnhanced\Traits;

/**
 * HasInjectedShieldPermissions
 *
 * Use this trait in child Livewire components that receive a pre-resolved
 * permission map from a parent Page that uses HasPageShield.
 *
 * Pattern in the parent Blade view:
 *
 *   @livewire('my-component', ['permissions' => $this->getShieldPermissions()])
 *
 * In the child component:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Traits\HasInjectedShieldPermissions;
 *
 *   class MyComponent extends Component
 *   {
 *       use HasInjectedShieldPermissions;
 *
 *       public function save(): void
 *       {
 *           if (! $this->canShield('editAllSettings')) {
 *               return;
 *           }
 *           // …
 *       }
 *   }
 *
 * The $permissions array is Livewire-synced, so the parent's auth state is
 * always the source of truth — the child never re-checks the database.
 */
trait HasInjectedShieldPermissions
{
    /**
     * Injected permission map: ['action' => true|false, …]
     * Populated by Livewire from the parent page's getShieldPermissions().
     *
     * @var array<string, bool>
     */
    public array $permissions = [];

    /**
     * Check a single action against the injected permission map.
     *
     * @param  string  $action  The action key (e.g. 'editAllSettings').
     */
    public function canShield(string $action): bool
    {
        return (bool) ($this->permissions[$action] ?? false);
    }

    /**
     * Assert a permission and abort (403) if the user does not hold it.
     * Useful as a guard at the start of Livewire action methods.
     *
     *   public function save(): void
     *   {
     *       $this->authorizeShield('editAllSettings');
     *       // …
     *   }
     */
    public function authorizeShield(string $action): void
    {
        abort_unless($this->canShield($action), 403);
    }
}
