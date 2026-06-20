<?php

namespace Agroezinger\FilamentShieldEnhanced\Forms;

use Agroezinger\FilamentShieldEnhanced\Support\ResourcePermissionKeyBuilder;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * EnhancedResourcePermissionsForm
 *
 * Builds the Filament form components for managing fine-grained resource
 * permissions inside a published RoleResource. Each Resource that declares
 * getShieldResourcePermissions() gets its own Section with individual checkboxes.
 *
 * ---
 * Usage in a published RoleResource form schema:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedResourcePermissionsForm;
 *
 *   Tabs\Tab::make('enhanced_resources')
 *       ->label('Ressourcen (Feinsteuerung)')
 *       ->schema(EnhancedResourcePermissionsForm::make()),
 * ---
 */
class EnhancedResourcePermissionsForm
{
    /**
     * Returns a map of CheckboxList field name => list of permission keys for
     * every enhanced Resource. Used to pre-fill the form in EditRole::mutateFormDataBeforeFill().
     *
     * @return array<string, list<string>>
     */
    public static function getResourcePermissionFields(): array
    {
        $result = [];

        foreach (static::discoverEnhancedResources() as $resource) {
            $fieldName          = static::fieldName($resource['class']);
            $result[$fieldName] = array_column($resource['permissions'], 'key');
        }

        return $result;
    }

    /**
     * Returns an array of Filament form components (Grid > Sections > CheckboxLists)
     * for every enhanced Resource discovered in the application.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        $resources = static::discoverEnhancedResources();

        if ($resources->isEmpty()) {
            return [];
        }

        $gridColumns = config('filament-shield-enhanced.ui.grid_columns', [
            'default' => 1,
            'sm'      => 2,
            'lg'      => 3,
        ]);

        $sections = $resources->map(fn (array $resource) => static::buildSection($resource))->all();

        return [
            Grid::make($gridColumns)->schema($sections),
        ];
    }

    // -------------------------------------------------------------------------
    // Section builder
    // -------------------------------------------------------------------------

    protected static function buildSection(array $resource): Section
    {
        /** @var class-string<Resource> $resourceClass */
        $resourceClass = $resource['class'];
        $permissions   = $resource['permissions'];

        $options = collect($permissions)
            ->mapWithKeys(fn (array $perm) => [$perm['key'] => $perm['label']])
            ->all();

        $descriptions = collect($permissions)
            ->filter(fn (array $perm) => filled($perm['description']))
            ->mapWithKeys(fn (array $perm) => [$perm['key'] => $perm['description']])
            ->all();

        $checkboxListColumns = config('filament-shield-enhanced.ui.checkbox_list_columns', [
            'default' => 1,
            'sm'      => 2,
        ]);

        $checkboxList = CheckboxList::make(static::fieldName($resourceClass))
            ->label('')
            ->options($options)
            ->columns($checkboxListColumns)
            ->gridDirection('row')
            ->bulkToggleable();

        if (! empty($descriptions)) {
            $checkboxList->descriptions($descriptions);
        }

        $title       = static::resolveSectionTitle($resourceClass);
        $description = static::resolveSectionDescription($resourceClass);

        return Section::make($title)
            ->description($description)
            ->compact()
            ->schema([$checkboxList]);
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * Discovers all Filament Resource classes that declare getShieldResourcePermissions()
     * and resolves their permission keys + labels.
     *
     * @return Collection<int, array{class: class-string, permissions: list<array{key: string, label: string, description: string|null}>}>
     */
    protected static function discoverEnhancedResources(): Collection
    {
        $allResources = collect();

        try {
            $panels = \Filament\Facades\Filament::getPanels();

            foreach ($panels as $panel) {
                foreach ($panel->getResources() as $resourceClass) {
                    if (
                        is_subclass_of($resourceClass, Resource::class)
                        && method_exists($resourceClass, 'getShieldResourcePermissions')
                    ) {
                        $allResources->push($resourceClass);
                    }
                }
            }
        } catch (\Throwable) {
            // Outside of a panel context (e.g. during unit testing).
        }

        return $allResources
            ->unique()
            ->map(fn (string $class) => [
                'class'       => $class,
                'permissions' => static::resolvePermissionsForResource($class),
            ])
            ->filter(fn (array $resource) => ! empty($resource['permissions']))
            ->values();
    }

    /**
     * Resolve permission key + human-readable label for each action declared
     * by the given Resource class.
     *
     * @param  class-string  $resourceClass
     * @return list<array{key: string, label: string, description: string|null}>
     */
    protected static function resolvePermissionsForResource(string $resourceClass): array
    {
        $actions   = $resourceClass::getShieldResourcePermissions();
        $subject   = class_basename($resourceClass::getModel());
        $separator = config('filament-shield.permissions.separator', ':');
        $case      = config('filament-shield.permissions.case', 'pascal');

        $result = [];

        foreach ($actions as $k => $v) {
            if (is_int($k)) {
                $action      = $v;
                $label       = static::humanizeAction($v);
                $description = null;
            } elseif (is_array($v)) {
                $action      = $k;
                $label       = $v['text'] ?? static::humanizeAction($k);
                $description = $v['description'] ?? null;
            } else {
                $action      = $k;
                $label       = $v;
                $description = null;
            }

            $result[] = [
                'key'         => ResourcePermissionKeyBuilder::build(
                    entity: $resourceClass,
                    affix: $action,
                    subject: $subject,
                    case: $case,
                    separator: $separator,
                ),
                'label'       => $label,
                'description' => $description,
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Label helpers
    // -------------------------------------------------------------------------

    protected static function resolveSectionTitle(string $resourceClass): string
    {
        try {
            return $resourceClass::getModelLabel();
        } catch (\Throwable) {
            return Str::headline(class_basename($resourceClass::getModel()));
        }
    }

    protected static function resolveSectionDescription(string $resourceClass): string
    {
        try {
            return $resourceClass::getSlug();
        } catch (\Throwable) {
            return $resourceClass;
        }
    }

    protected static function humanizeAction(string $action): string
    {
        return Str::headline($action);
    }

    protected static function fieldName(string $resourceClass): string
    {
        return 'resource_permissions_' . Str::snake(class_basename($resourceClass::getModel()));
    }
}
