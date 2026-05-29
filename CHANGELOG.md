# Changelog

All notable changes to `filament-shield-enhanced` will be documented in this file.

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
