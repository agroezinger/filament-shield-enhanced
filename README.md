# Filament Shield Enhanced

A standalone addon for [bezhansalleh/filament-shield](https://github.com/bezhanSalleh/filament-shield) that adds **fine-grained page permissions** and a **structured Role Resource UI** — without forking or replacing the original package.

> **Why this exists.**  
> The features were proposed upstream in [bezhanSalleh/filament-shield#698](https://github.com/bezhanSalleh/filament-shield/issues/698). The author has not had time to review the PR. This addon ships the same functionality as a composable layer on top of the official package.

---

## Features

| Feature | Description |
|---|---|
| **Multi-action page permissions** | Declare several permissions per page via `getShieldPagePermissions()`. |
| **`canShield('action')`** | Fluent, type-safe permission check inside a Page class or its Blade view. |
| **`getShieldPermissions()`** | Returns a pre-resolved `action → bool` map for injection into child Livewire components. |
| **`HasInjectedShieldPermissions`** | Trait for child Livewire components that receive the map from the parent page. |
| **`EnhancedPagePermissionsForm`** | Form builder helper for the published RoleResource — renders each enhanced page as a separate Section with individual checkboxes. |
| **Three-part key convention** | `{Prefix}{sep}{Action}{sep}{Subject}` (e.g. `Page:EditSettings:SettingsPage`) — fully respects filament-shield's `separator` and `case` config. |
| **Zero conflict** | Does not replace any original class. Falls back gracefully on pages that do not declare `getShieldPagePermissions()`. |

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 |
| Filament | ^4.0 \| ^5.0 |
| bezhansalleh/filament-shield | ^4.0 |

---

## Installation

```bash
composer require agroezinger/filament-shield-enhanced
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag="filament-shield-enhanced-config"
```

---

## Usage

### 1 — Declare fine-grained permissions on a Page

Replace (or complement) the original `HasPageShield` with the enhanced version:

```php
<?php

namespace App\Filament\Pages;

use Agroezinger\FilamentShieldEnhanced\Traits\HasPageShield;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    use HasPageShield;

    /**
     * Declare every action that can be independently granted on this page.
     * The 'view' action controls whether the user can navigate to the page at all.
     *
     * You can optionally add a human-readable description for each action by
     * using an associative key => description format. Plain string entries and
     * described entries can be mixed freely.
     */
    public static function getShieldPagePermissions(): array
    {
        return [
            'view',
            'editGlobalSettings' => 'Can change global application settings',
            'exportData'         => 'Can export data as CSV / Excel',
        ];
    }
}
```

Then run the enhanced generator to create the permissions in the database:

```bash
php artisan shield:generate-enhanced-pages --all-panels
```

> `shield:generate-enhanced-pages` is provided by this addon and only processes pages that declare `getShieldPagePermissions()`. Use `--panel=<id>` to limit the scan to a single panel.

This will create three permissions for the page above:

```
Page:View:SettingsPage
Page:EditGlobalSettings:SettingsPage
Page:ExportData:SettingsPage
```

> The prefix, separator and case all come from `config('filament-shield-enhanced.pages.permission_prefix')` and filament-shield's own `permissions.separator` / `permissions.case` — so the keys look consistent with your Resource permissions.

---

### 2 — Check permissions in PHP

```php
// Inside the Page class
if ($this->canShield('editGlobalSettings')) {
    // Perform restricted action
}
```

```blade
{{-- Inside the Page Blade view --}}
@if($this->canShield('exportData'))
    <x-filament::button wire:click="export">Export</x-filament::button>
@endif
```

---

### 3 — Inject permissions into child Livewire components

**Parent page Blade:**

```blade
@livewire('settings-sidebar', [
    'permissions' => $this->getShieldPermissions()
])
```

**Child Livewire component:**

```php
<?php

namespace App\Livewire;

use Agroezinger\FilamentShieldEnhanced\Traits\HasInjectedShieldPermissions;
use Livewire\Component;

class SettingsSidebar extends Component
{
    use HasInjectedShieldPermissions;

    // $this->permissions is automatically populated by Livewire.

    public function save(): void
    {
        $this->authorizeShield('editGlobalSettings'); // aborts 403 if not permitted
        // … save logic
    }

    public function render()
    {
        return view('livewire.settings-sidebar');
    }
}
```

---

### 4 — Structured UI in the published RoleResource

After publishing the RoleResource with `php artisan shield:publish --panel=admin` two files need small changes.

#### 4a — RoleResource: add the enhanced section

Open the published `RoleResource.php` and add a new `Section` directly after `static::getShieldFormComponents()` in the `form()` method:

```php
use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedPagePermissionsForm;
use Filament\Schemas\Components\Section;

<<<<<<< Updated upstream
// Inside the form schema - after:
static::getShieldFormComponents(),

// Zusätzliche Section für fine-grained page permissions:
=======
// Inside the form schema, after static::getShieldFormComponents():
static::getShieldFormComponents(),

>>>>>>> Stashed changes
Section::make('Pages (Enhanced)')
    ->schema(EnhancedPagePermissionsForm::make())
    ->columnSpanFull(),
```

Each page that declares `getShieldPagePermissions()` will appear as its own **Section** containing individual checkboxes — one per action. Pages without the method are not affected.

#### 4b — EditRole: add the pre-fill trait

Open the published `EditRole.php` and add `use HasEnhancedRoleForm` to the class. This is the **only** change required — it makes the page-permission checkboxes reflect the role's existing permissions when the form opens.

```php
use Agroezinger\FilamentShieldEnhanced\Traits\HasEnhancedRoleForm;

class EditRole extends EditRecord
{
    use HasEnhancedRoleForm;

    // … rest of the file unchanged
}
```

---

## Configuration

```php
// config/filament-shield-enhanced.php

return [
    'pages' => [
        // First segment of the three-part key: Page:Action:Subject
        'permission_prefix' => 'Page',
    ],

    'ui' => [
        'grid_columns' => [
            'default' => 1,
            'sm'      => 2,
            'lg'      => 3,
        ],

        'checkbox_list_columns' => [
            'default' => 1,
            'sm'      => 2,
        ],
    ],
];
```

---

## How it works internally

This addon does **not** override any class from filament-shield. Instead it uses the package's public extension point:

```php
FilamentShield::buildPermissionKeyUsing(function (...) { ... });
```

When a Page class exposes `getShieldPagePermissions()`, the addon intercepts the key builder and applies its three-part naming convention. All other entities (Resources, Widgets, regular Pages) are delegated back to the original builder unchanged.

---

## Upgrading from the fork

If you previously used the `agroezinger/filament-shield` fork (which is a modified copy of the original package):

1. Switch `composer.json` back to the official package:
   ```bash
   composer remove agroezinger/filament-shield
   composer require bezhansalleh/filament-shield agroezinger/filament-shield-enhanced
   ```
2. Replace `use BezhanSalleh\FilamentShield\Traits\HasPageShield` with  
   `use Agroezinger\FilamentShieldEnhanced\Traits\HasPageShield` in your pages.
3. Replace `use BezhanSalleh\FilamentShield\Traits\HasInjectedShieldPermissions` (if used) with  
   `use Agroezinger\FilamentShieldEnhanced\Traits\HasInjectedShieldPermissions`.
4. Re-run `php artisan shield:generate --all` so the new three-part keys are created.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE.md](LICENSE.md).

## Credits

- [Alexander Groezinger](https://github.com/agroezinger) — addon author
- [Bezhan Salleh](https://github.com/bezhanSalleh) — original filament-shield package
