# Changelog

All notable changes to `filament-shield-enhanced` will be documented in this file.

## [0.0.6] - 2026-06-20

### Added
- **`HasResourceShield` trait** — add to any Filament `Resource` class to declare fine-grained custom permissions beyond the standard CRUD policy methods. Provides `canShield(string $action): bool` (with super-admin bypass) and `getShieldPermissions(): array` (action → bool map) as static methods.
- **`ResourcePermissionKeyBuilder`** — builds two-part permission keys (`Action:ModelBasename`, e.g. `ViewContactInfo:Member`) that match filament-shield's own resource permission format. No prefix is added, so custom actions are visually consistent with CRUD permissions.
- **`EnhancedResourcePermissionsForm`** — form builder helper for the published `RoleResource`. Discovers all Resources that declare `getShieldResourcePermissions()` and renders each as a separate `Section` with individual checkboxes — one per declared action. Supports the same label formats as pages (`'action'`, `'action' => 'Label'`, `'action' => ['text' => '...', 'description' => '...']`).
- **`ShieldGenerateEnhancedResources` Artisan command** (`shield:generate-enhanced-resources`) — creates Spatie permission records for every action declared in `getShieldResourcePermissions()`. Accepts `--panel=<id>` or `--all-panels`.
- **`HasEnhancedRoleForm` extended** — `mutateFormDataBeforeFill()` now also pre-fills resource-permission checkboxes in addition to page-permission checkboxes. No change is required in the published `EditRole` page — the trait update is transparent.

## [0.0.5] - 2026-06-10

### Added
- `FilamentShieldEnhancedPlugin` — empty `Plugin` implementation so the addon can optionally be registered via `->plugin(FilamentShieldEnhancedPlugin::make())` on a panel. No functional change; all logic remains in the `ServiceProvider`.

### Changed
- `composer.json`: removed hardcoded `version` field (version is now tracked via git tags only).
- `composer.json`: broadened compatibility — `illuminate/contracts` and `illuminate/support` now accept `^13.0`; `orchestra/testbench` now accepts `^11.0`.

## [0.0.4] - 2026-06-10

### Changed
- README improvements: clarified `getShieldFormComponents()` / `getPageOptions()` integration pattern with updated code examples.

## [0.0.3] - 2026-06-02

### Changed
- **RoleResource integration pattern** (breaking for apps that followed the 0.0.2 instructions):  
  The `Section::make('Pages (Enhanced)')` approach is replaced by two method overrides in the published `RoleResource`:
  - `getShieldFormComponents()` — appends a dedicated **"Seiten (Feinsteuerung)"** tab after Shield's built-in tabs instead of rendering a free-floating section beneath them. The tab shows a badge with the number of enhanced pages.
  - `getPageOptions()` — filters pages that declare `getShieldPagePermissions()` out of Shield's standard "Seiten" tab. Previously `Page:View:*` for enhanced pages appeared in both the standard tab and the enhanced section simultaneously (same permission key, two checkboxes). The override eliminates that duplication: enhanced pages are now managed exclusively in the Enhanced tab.

### Fixed
- **Duplicate `Page:View:*` checkboxes** — Pages implementing `getShieldPagePermissions()` are no longer shown in Shield's built-in "Seiten" tab. Their `view` action (and all others) is managed solely in the new Enhanced tab.

### Migration from 0.0.2

Replace the old static `Section` in `RoleResource.php`:

```php
// Remove this:
Section::make('Pages (Enhanced)')
    ->schema(EnhancedPagePermissionsForm::make())
    ->columnSpanFull(),
```

Add the two method overrides described in [README § 4a](README.md#4a--roleresouce-add-the-enhanced-tab-and-remove-duplicates).

## [0.0.2] - 2026-05-29

### Added
- `getShieldPagePermissions()` now accepts an optional inline help text per action via the array format `'action' => ['text' => '...', 'description' => '...']`. The description is shown below the checkbox in the RoleResource UI using Filament's native `->descriptions()`.
- `HasEnhancedRoleForm` trait for the published `EditRole` page — pre-fills page-permission checkboxes with the role's existing permissions on load.
- `ShieldGenerateEnhancedPages` Artisan command (`shield:generate-enhanced-pages`) is now shipped with the package and no longer needs to be created manually in the application.
- Section titles in the RoleResource now resolve from `$navigationLabel → $title → $heading`, so wizard-style pages (e.g. those with only an instance `$heading`) display a meaningful label.
- Section descriptions now show the page slug instead of the FQCN.
- `getShieldPagePermissions()` supports mixed associative/numeric arrays: `'action'` (auto-label), `'action' => 'Label'`, and `'action' => ['text' => 'Label', 'description' => 'Hint']`.

## [0.0.1] - 2026-05-27

### Added
- `HasPageShield` trait with `getShieldPagePermissions()`, `canShield()`, `getShieldPermissions()`.
- `HasInjectedShieldPermissions` trait for child Livewire components.
- `EnhancedPagePermissionsForm` form builder for published RoleResource.
- `FilamentShieldEnhancedServiceProvider` with non-destructive permission key hook.
- Config file with prefix, separator and UI layout options.
- English translations.
