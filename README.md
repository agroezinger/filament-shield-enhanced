# Filament Shield Enhanced

[![Plumb score](https://plumbphp.dev/badges/agroezinger/filament-shield-enhanced/composite.svg)](https://plumbphp.dev/agroezinger/filament-shield-enhanced)

> [!WARNING]
> **Testing Phase:** Versions `0.0.*` are currently in the testing phase. At present, there are no known bugs.

A standalone addon for [bezhansalleh/filament-shield](https://github.com/bezhanSalleh/filament-shield) that adds **fine-grained page and resource permissions** and a **structured Role Resource UI** — without forking or replacing the original package.

> **Why this exists.**  
> The features were proposed upstream in [bezhanSalleh/filament-shield#698](https://github.com/bezhanSalleh/filament-shield/issues/698). The author has not had time to review the PR. This addon ships the same functionality as a composable layer on top of the official package.

---

## Features

| Feature                               | Description                                                                                                                                     |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| **Multi-action page permissions**     | Declare several permissions per page via `getShieldPagePermissions()`.                                                                          |
| **Multi-action resource permissions** | Declare custom permissions per resource via `getShieldResourcePermissions()` — beyond the standard CRUD policy methods.                         |
| **`canShield('action')`**             | Fluent, type-safe permission check — instance method on Pages, static method on Resources.                                                      |
| **`getShieldPermissions()`**          | Returns a pre-resolved `action → bool` map for injection into child Livewire components.                                                        |
| **`HasInjectedShieldPermissions`**    | Trait for child Livewire components that receive the map from the parent page.                                                                  |
| **`EnhancedPagePermissionsForm`**     | Form builder helper for the published RoleResource — renders each enhanced page as a separate Section with individual checkboxes.               |
| **`EnhancedResourcePermissionsForm`** | Form builder helper for the published RoleResource — renders each enhanced resource as a separate Section with individual checkboxes.           |
| **Three-part page key convention**    | `{Prefix}{sep}{Action}{sep}{Subject}` (e.g. `Page:EditSettings:SettingsPage`) — fully respects filament-shield's `separator` and `case` config. |
| **Two-part resource key convention**  | `{Action}{sep}{ModelBasename}` (e.g. `ViewContactInfo:Member`) — matches Shield's own resource permission format, no extra prefix.              |
| **Zero conflict**                     | Does not replace any original class. Falls back gracefully on entities that do not declare the method.                                          |

---

## Requirements

| Dependency                   | Version                 |
| ---------------------------- | ----------------------- |
| PHP                          | ^8.2                    |
| Laravel                      | ^11.0 \| ^12.0 \| ^13.0 |
| Filament                     | ^4.0 \| ^5.0            |
| bezhansalleh/filament-shield | ^4.0                    |

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

## Usage — Pages

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
     * Three entry formats can be mixed freely:
     *
     *   'action'                          → label auto-generated from action name
     *   'action' => 'Label'               → explicit label
     *   'action' => ['text'        => 'Label',
     *                'description' => 'Shown below the checkbox in the role editor']
     */
    public static function getShieldPagePermissions(): array
    {
        return [
            'view'               => 'Can view this page',
            'editGlobalSettings' => [
                'text'        => 'Can change global settings',
                'description' => 'Grants access to all fields in the Global Settings section.',
            ],
            'exportData'         => 'Can export data as CSV / Excel',
        ];
    }
}
```

Then run the enhanced generator to create the permissions in the database:

```bash
php artisan shield:generate-enhanced-pages --all-panels
```

> Use `--panel=<id>` to limit the scan to a single panel.

This will create three permissions for the page above:

```
Page:View:SettingsPage
Page:EditGlobalSettings:SettingsPage
Page:ExportData:SettingsPage
```

---

### 2 — Check permissions in PHP (Pages)

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

## Usage — Resources

### 4 — Declare fine-grained permissions on a Resource

Add `HasResourceShield` to any Filament Resource and declare custom actions via `getShieldResourcePermissions()`:

```php
<?php

namespace App\Filament\Resources;

use Agroezinger\FilamentShieldEnhanced\Traits\HasResourceShield;
use App\Models\Member;
use Filament\Resources\Resource;

class MemberResource extends Resource
{
    use HasResourceShield;

    protected static ?string $model = Member::class;

    /**
     * Declare custom permissions beyond the standard CRUD policy methods.
     * Keys are action names; values are human-readable labels (shown in the role editor).
     *
     * Same three entry formats as getShieldPagePermissions():
     *   'action'                                   → auto-generated label
     *   'action' => 'Label'                        → explicit label
     *   'action' => ['text' => '...', 'description' => '...']
     */
    public static function getShieldResourcePermissions(): array
    {
        return [
            'Export'          => 'Export member list (basic data)',
            'ExportFinance'   => 'Export member list including financial data (IBAN, fees)',
            'ViewContactInfo' => 'View contact details (email, phone, address)',
            'ViewBankingInfo' => 'View bank details (IBAN, BIC, account holder)',
        ];
    }
}
```

Then create the permissions in the database:

```bash
php artisan shield:generate-enhanced-resources --all-panels
```

This will create (for the example above):

```
Export:Member
ExportFinance:Member
ViewContactInfo:Member
ViewBankingInfo:Member
```

The key format (`Action:ModelBasename`) is identical to Shield's own resource permission format so everything looks consistent.

---

### 5 — Check resource permissions in PHP

`canShield()` is a **static** method on Resources (unlike Pages, where it is an instance method):

```php
// Anywhere in your application
if (MemberResource::canShield('ViewContactInfo')) {
    // show contact section
}

// Returns ['Export' => true, 'ViewContactInfo' => false, …]
$permissions = MemberResource::getShieldPermissions();
```

Super-admin bypass is applied automatically — identical behaviour to the page trait.

---

### 6 — Structured UI in the published RoleResource

After publishing the RoleResource with `php artisan shield:publish --panel=<id>` two files need small changes.

#### 6a — RoleResource: add both enhanced tabs

Open the published `RoleResource.php` and override two methods:

```php
use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedPagePermissionsForm;
use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedResourcePermissionsForm;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Exclude pages that declare getShieldPagePermissions() from the standard
 * "Pages" tab — they are managed exclusively by the Enhanced tab.
 */
public static function getPageOptions(): array
{
    return collect(FilamentShield::getPages())
        ->reject(fn(array $page) => method_exists($page['pageFqcn'], 'getShieldPagePermissions'))
        ->flatMap(fn(array $page) => $page['permissions'])
        ->toArray();
}

public static function getShieldFormComponents(): \Filament\Schemas\Components\Component
{
    $enhancedPageComponents     = EnhancedPagePermissionsForm::make();
    $enhancedPageCount          = count(EnhancedPagePermissionsForm::getPagePermissionFields());

    $enhancedResourceComponents = EnhancedResourcePermissionsForm::make();
    $enhancedResourceCount      = count(EnhancedResourcePermissionsForm::getResourcePermissionFields());

    $tabs = [
        static::getTabFormComponentForResources(),
        static::getTabFormComponentForPage(),
        static::getTabFormComponentForWidget(),
        static::getTabFormComponentForCustomPermissions(),
    ];

    if (! empty($enhancedResourceComponents)) {
        $tabs[] = Tab::make('enhanced_resources')
            ->label('Resources (Fine-grained)')
            ->badge($enhancedResourceCount ?: null)
            ->schema($enhancedResourceComponents);
    }

    if (! empty($enhancedPageComponents)) {
        $tabs[] = Tab::make('enhanced_pages')
            ->label('Pages (Fine-grained)')
            ->badge($enhancedPageCount ?: null)
            ->schema($enhancedPageComponents);
    }

    return Tabs::make('Permissions')
        ->contained()
        ->tabs($tabs)
        ->columnSpan('full');
}
```

Each Resource that declares `getShieldResourcePermissions()` appears in the **"Resources (Fine-grained)"** tab as its own Section with individual checkboxes.

> **Note:** Shield's standard "Resources" tab only shows CRUD policy method permissions (`ViewAny`, `Create`, `Update`, …). Custom resource actions do **not** appear there — no duplicate-filtering override is needed.

#### 6b — EditRole: add the pre-fill trait

Open the published `EditRole.php` and add `use HasEnhancedRoleForm`. This pre-fills **both** page- and resource-permission checkboxes when the form opens.

```php
use Agroezinger\FilamentShieldEnhanced\Traits\HasEnhancedRoleForm;

class EditRole extends EditRecord
{
    use HasEnhancedRoleForm;

    // … rest of the file unchanged
}
```

The `mutateFormDataBeforeSave()` / `afterSave()` logic from Shield's own `EditRole` handles saving — no additional overrides needed.

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

Resource permissions use a two-part format matching Shield's own convention and are **not** created via `shield:generate` — only via `shield:generate-enhanced-resources`. This means the hook is not involved for Resources at all.

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
