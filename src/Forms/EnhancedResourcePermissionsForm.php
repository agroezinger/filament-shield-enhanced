<?php

namespace Agroezinger\FilamentShieldEnhanced\Forms;

use Agroezinger\FilamentShieldEnhanced\Support\NavigationGroupResolver;
use Agroezinger\FilamentShieldEnhanced\Support\ResourcePermissionKeyBuilder;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * EnhancedResourcePermissionsForm
 *
 * Builds the Filament form components for managing permissions of every
 * Filament Resource inside a published RoleResource: one Section per
 * Resource, combining the standard CRUD permissions (view/create/update/…,
 * sourced from bezhansalleh/filament-shield) with the fine-grained custom
 * actions a Resource may additionally declare via getShieldResourcePermissions().
 * Sections are grouped into sub-tabs by the Resource's own navigation group,
 * in the order the panel declares its navigation groups.
 *
 * ---
 * Usage in a published RoleResource form schema:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedResourcePermissionsForm;
 *
 *   Tabs\Tab::make('resources')
 *       ->label('Ressourcen')
 *       ->schema(EnhancedResourcePermissionsForm::make()),
 * ---
 */
class EnhancedResourcePermissionsForm
{
    /**
     * Returns a map of CheckboxList field name => list of permission keys for
     * every Resource (standard + fine-grained combined). Used to pre-fill the
     * form in EditRole::mutateFormDataBeforeFill().
     *
     * @return array<string, list<string>>
     */
    public static function getResourcePermissionFields(): array
    {
        $result = [];

        foreach (static::discoverResources() as $resource) {
            $fieldName          = static::fieldName($resource['class']);
            $result[$fieldName] = array_keys($resource['options']);
        }

        return $result;
    }

    /**
     * Returns an array of Filament form components — one outer Tabs component
     * with one inner Tab per navigation group, each containing a Grid of
     * Resource Sections — for every Resource discovered in the application.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function make(): array
    {
        $resources = static::discoverResources();

        if ($resources->isEmpty()) {
            return [];
        }

        $groupOrder = NavigationGroupResolver::order();

        $gridColumns = config('filament-shield-enhanced.ui.grid_columns', [
            'default' => 1,
            'sm'      => 2,
            'lg'      => 3,
        ]);

        $groupTabs = $resources
            ->groupBy(fn (array $resource) => $resource['navigationGroup'])
            ->sortBy(function (Collection $group, string $label) use ($groupOrder): int {
                $position = array_search($label, $groupOrder, true);

                return $position === false ? count($groupOrder) : $position;
            })
            ->map(function (Collection $group, string $label) use ($gridColumns): Tab {
                $sections = $group
                    ->sortBy('navigationSort')
                    ->map(fn (array $resource) => static::buildSection($resource))
                    ->all();

                return Tab::make(Str::slug($label !== '' ? $label : 'sonstige'))
                    ->label($label !== '' ? $label : 'Sonstige')
                    ->badge($group->count())
                    ->schema([
                        Grid::make($gridColumns)->schema($sections),
                    ]);
            })
            ->values()
            ->all();

        return [
            Tabs::make('resource_groups')
                ->tabs($groupTabs)
                ->columnSpanFull(),
        ];
    }

    // -------------------------------------------------------------------------
    // Section builder
    // -------------------------------------------------------------------------

    public static function buildSection(array $resource): Section
    {
        /** @var class-string<Resource> $resourceClass */
        $resourceClass = $resource['class'];
        $options       = $resource['options'];
        $descriptions  = $resource['descriptions'];

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
            ->collapsible()
            ->schema([$checkboxList]);
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * Discovers every Resource Filament Shield knows about and resolves its
     * combined permission options (standard CRUD + fine-grained custom
     * actions, if declared) plus the navigation group/sort used for grouping.
     *
     * @return Collection<int, array{
     *     class: class-string,
     *     options: array<string, string>,
     *     descriptions: array<string, string>,
     *     navigationGroup: string,
     *     navigationSort: int,
     * }>
     */
    public static function discoverResources(): Collection
    {
        return collect(FilamentShield::getResources())
            ->map(function (array $entity) {
                /** @var class-string<Resource> $resourceClass */
                $resourceClass = $entity['resourceFqcn'];

                $options      = FilamentShield::getResourcePermissionsWithLabels($resourceClass) ?? [];
                $descriptions = [];

                if (method_exists($resourceClass, 'getShieldResourcePermissions')) {
                    foreach (static::resolvePermissionsForResource($resourceClass) as $permission) {
                        $options[$permission['key']] = $permission['label'];

                        if (filled($permission['description'])) {
                            $descriptions[$permission['key']] = $permission['description'];
                        }
                    }
                }

                if (method_exists($resourceClass, 'getShieldPermissionDescriptions')) {
                    foreach ($resourceClass::getShieldPermissionDescriptions() as $key => $description) {
                        if (filled($description)) {
                            $descriptions[$key] = __($description);
                        }
                    }
                }

                return [
                    'class'           => $resourceClass,
                    'options'         => $options,
                    'descriptions'    => $descriptions,
                    'navigationGroup' => NavigationGroupResolver::labelFor($resourceClass),
                    'navigationSort'  => method_exists($resourceClass, 'getNavigationSort')
                        ? ($resourceClass::getNavigationSort() ?? PHP_INT_MAX)
                        : PHP_INT_MAX,
                ];
            })
            ->filter(fn (array $resource) => ! empty($resource['options']))
            ->values();
    }

    /**
     * Resolve permission key + human-readable label for each fine-grained
     * action declared by the given Resource class via getShieldResourcePermissions().
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
                // __(): these labels/descriptions are literal strings declared by the
                // consuming app, not translation keys — routes them through the app's
                // own lang/{locale}.json (short-key JSON translation) if it provides
                // one, no-op otherwise. Same mechanism as resolveSectionTitle() below.
                'label'       => __($label),
                'description' => $description !== null ? __($description) : null,
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
            return __($resourceClass::getModelLabel());
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
