<?php

namespace Agroezinger\FilamentShieldEnhanced\Forms;

use Agroezinger\FilamentShieldEnhanced\Support\ComponentDiscovery;
use Agroezinger\FilamentShieldEnhanced\Support\ComponentPermissionKeyBuilder;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * EnhancedComponentPermissionsForm
 *
 * Builds the Filament form components for managing fine-grained component
 * permissions inside a published RoleResource. Each Livewire component that
 * declares getShieldComponentPermissions() gets its own Section with
 * individual checkboxes — same layout as EnhancedPagePermissionsForm.
 *
 * ---
 * Usage in a published RoleResource form schema:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedComponentPermissionsForm;
 *   use Filament\Forms\Components\Tabs;
 *
 *   Tabs\Tab::make('Components')
 *       ->schema(EnhancedComponentPermissionsForm::make()),
 * ---
 */
class EnhancedComponentPermissionsForm
{
    /**
     * Returns a map of CheckboxList field name => list of permission keys for
     * every enhanced component. Used to pre-fill the form in
     * EditRole::mutateFormDataBeforeFill() (see HasEnhancedRoleForm).
     *
     * @return array<string, list<string>>
     */
    public static function getComponentPermissionFields(): array
    {
        $result = [];

        foreach (static::discoverEnhancedComponents() as $component) {
            $fieldName = 'component_permissions_'.Str::snake(class_basename($component['class']));
            $result[$fieldName] = array_column($component['permissions'], 'key');
        }

        return $result;
    }

    /**
     * Returns an array of Filament form components (Grid > Sections > CheckboxLists)
     * for every enhanced component discovered in the application.
     *
     * @return list<Component>
     */
    public static function make(): array
    {
        $components = static::discoverEnhancedComponents();

        if ($components->isEmpty()) {
            return [];
        }

        $gridColumns = config('filament-shield-enhanced.ui.grid_columns', [
            'default' => 1,
            'sm' => 2,
            'lg' => 2,
        ]);

        $sections = $components->map(fn (array $component) => static::buildSection($component))->all();

        return [
            Grid::make($gridColumns)->schema($sections),
        ];
    }

    // -------------------------------------------------------------------------
    // Section builder
    // -------------------------------------------------------------------------

    protected static function buildSection(array $component): Section
    {
        $componentClass = $component['class'];
        $permissions = $component['permissions']; // [['key' => '...', 'label' => '...'], …]

        $title = Str::headline(class_basename($componentClass));

        $options = collect($permissions)
            ->mapWithKeys(fn (array $perm) => [$perm['key'] => $perm['label']])
            ->all();

        $checkboxListColumns = config('filament-shield-enhanced.ui.checkbox_list_columns', [
            'default' => 1,
            'sm' => 2,
        ]);

        $checkboxList = CheckboxList::make('component_permissions_'.Str::snake(class_basename($componentClass)))
            ->label('')
            ->options($options)
            ->columns($checkboxListColumns)
            ->gridDirection('row')
            ->bulkToggleable();

        return Section::make($title)
            ->description($componentClass)
            ->compact()
            ->schema([$checkboxList]);
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * Discovers all Livewire components using HasComponentShield and resolves
     * their permission keys + labels.
     *
     * @return Collection<int, array{class: class-string, permissions: list<array{key: string, label: string}>}>
     */
    protected static function discoverEnhancedComponents(): Collection
    {
        return ComponentDiscovery::discover()
            ->map(fn (string $class) => [
                'class' => $class,
                'permissions' => static::resolvePermissionsForComponent($class),
            ])
            ->filter(fn (array $component) => ! empty($component['permissions']))
            ->values();
    }

    /**
     * Resolve permission key + human-readable label for each action declared
     * by the given component class.
     *
     * @param  class-string  $componentClass
     * @return list<array{key: string, label: string}>
     */
    protected static function resolvePermissionsForComponent(string $componentClass): array
    {
        $actions = $componentClass::getShieldComponentPermissions();
        $subject = class_basename($componentClass);
        $separator = config('filament-shield.permissions.separator', ':');
        $case = config('filament-shield.permissions.case', 'pascal');

        $result = [];

        foreach ($actions as $k => $v) {
            if (is_int($k)) {
                $action = $v;
                $label = Str::headline($v);
            } else {
                $action = $k;
                $label = is_string($v) ? $v : Str::headline($k);
            }

            $result[] = [
                'key' => ComponentPermissionKeyBuilder::build(
                    entity: $componentClass,
                    affix: $action,
                    subject: $subject,
                    case: $case,
                    separator: $separator,
                ),
                'label' => $label,
            ];
        }

        return $result;
    }
}
