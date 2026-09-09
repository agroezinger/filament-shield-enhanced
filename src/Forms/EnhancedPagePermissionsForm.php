<?php

namespace Agroezinger\FilamentShieldEnhanced\Forms;

use Agroezinger\FilamentShieldEnhanced\Support\NavigationGroupResolver;
use Agroezinger\FilamentShieldEnhanced\Support\PagePermissionKeyBuilder;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\BasePage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * EnhancedPagePermissionsForm
 *
 * Builds the Filament form components for managing permissions of every
 * Filament Page inside a published RoleResource: one Section per Page,
 * combining the standard permission bezhansalleh/filament-shield derives
 * for it with the fine-grained custom actions a Page may additionally
 * declare via getShieldPagePermissions(). Sections are grouped into
 * sub-tabs by the Page's own navigation group, in the order the panel
 * declares its navigation groups.
 *
 * ---
 * Usage in a published RoleResource form schema:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedPagePermissionsForm;
 *
 *   Tabs\Tab::make('pages')
 *       ->label('Seiten')
 *       ->schema(EnhancedPagePermissionsForm::make()),
 * ---
 */
class EnhancedPagePermissionsForm
{
    /**
     * Returns a map of CheckboxList field name => list of permission keys for
     * every Page (standard + fine-grained combined). Used to pre-fill the
     * form in EditRole::mutateFormDataBeforeFill().
     *
     * @return array<string, list<string>>
     */
    public static function getPagePermissionFields(): array
    {
        $result = [];

        foreach (static::discoverPages() as $page) {
            $fieldName          = static::fieldName($page['class']);
            $result[$fieldName] = array_keys($page['options']);
        }

        return $result;
    }

    /**
     * Returns an array of Filament form components — one outer Tabs component
     * with one inner Tab per navigation group, each containing a Grid of
     * Page Sections — for every Page discovered in the application.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function make(): array
    {
        $pages = static::discoverPages();

        if ($pages->isEmpty()) {
            return [];
        }

        $groupOrder = NavigationGroupResolver::order();

        $gridColumns = config('filament-shield-enhanced.ui.grid_columns', [
            'default' => 1,
            'sm'      => 2,
            'lg'      => 3,
        ]);

        $groupTabs = $pages
            ->groupBy(fn (array $page) => $page['navigationGroup'])
            ->sortBy(function (Collection $group, string $label) use ($groupOrder): int {
                $position = array_search($label, $groupOrder, true);

                return $position === false ? count($groupOrder) : $position;
            })
            ->map(function (Collection $group, string $label) use ($gridColumns): Tab {
                $sections = $group
                    ->sortBy('navigationSort')
                    ->map(fn (array $page) => static::buildSection($page))
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
            Tabs::make('page_groups')
                ->tabs($groupTabs)
                ->columnSpanFull(),
        ];
    }

    // -------------------------------------------------------------------------
    // Section builder
    // -------------------------------------------------------------------------

    public static function buildSection(array $page): Section
    {
        /** @var class-string<BasePage> $pageClass */
        $pageClass    = $page['class'];
        $options      = $page['options'];
        $descriptions = $page['descriptions'];

        $checkboxListColumns = config('filament-shield-enhanced.ui.checkbox_list_columns', [
            'default' => 1,
            'sm'      => 2,
        ]);

        $checkboxList = CheckboxList::make(static::fieldName($pageClass))
            ->label('')
            ->options($options)
            ->columns($checkboxListColumns)
            ->gridDirection('row')
            ->bulkToggleable();

        if (! empty($descriptions)) {
            $checkboxList->descriptions($descriptions);
        }

        return Section::make(static::resolveSectionTitle($pageClass))
            ->description(static::resolveSectionDescription($pageClass))
            ->compact()
            ->collapsible()
            ->schema([$checkboxList]);
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * Discovers every Page Filament Shield knows about and resolves its
     * combined permission options (standard + fine-grained custom actions,
     * if declared) plus the navigation group/sort used for grouping.
     *
     * @return Collection<int, array{
     *     class: class-string,
     *     options: array<string, string>,
     *     descriptions: array<string, string>,
     *     navigationGroup: string,
     *     navigationSort: int,
     * }>
     */
    public static function discoverPages(): Collection
    {
        return collect(FilamentShield::getPages())
            ->map(function (array $entity) {
                /** @var class-string<BasePage> $pageClass */
                $pageClass = $entity['pageFqcn'];

                // Unlike resources (multiple affixes → list of ['key','label'] structs),
                // filament-shield derives pages/widgets from a single string prefix, so
                // $entity['permissions'] here is already the flat [key => label] map —
                // one entry (typically 'view') per page.
                $options = $entity['permissions'] ?? [];

                $descriptions = [];

                if (method_exists($pageClass, 'getShieldPagePermissions')) {
                    foreach (static::resolvePermissionsForPage($pageClass) as $permission) {
                        $options[$permission['key']] = $permission['label'];

                        if (filled($permission['description'])) {
                            $descriptions[$permission['key']] = $permission['description'];
                        }
                    }
                }

                if (method_exists($pageClass, 'getShieldPermissionDescriptions')) {
                    foreach ($pageClass::getShieldPermissionDescriptions() as $key => $description) {
                        if (filled($description)) {
                            $descriptions[$key] = __($description);
                        }
                    }
                }

                return [
                    'class'           => $pageClass,
                    'options'         => $options,
                    'descriptions'    => $descriptions,
                    'navigationGroup' => NavigationGroupResolver::labelFor($pageClass),
                    'navigationSort'  => method_exists($pageClass, 'getNavigationSort')
                        ? ($pageClass::getNavigationSort() ?? PHP_INT_MAX)
                        : PHP_INT_MAX,
                ];
            })
            ->filter(fn (array $page) => ! empty($page['options']))
            ->values();
    }

    /**
     * Resolve permission key + human-readable label for each fine-grained
     * action declared by the given Page class via getShieldPagePermissions().
     *
     * @param  class-string  $pageClass
     * @return list<array{key: string, label: string, description: string|null}>
     */
    protected static function resolvePermissionsForPage(string $pageClass): array
    {
        $actions   = $pageClass::getShieldPagePermissions();
        $subject   = class_basename($pageClass);
        $separator = config('filament-shield.permissions.separator', ':');
        $case      = config('filament-shield.permissions.case', 'pascal');

        $result = [];

        foreach ($actions as $k => $v) {
            if (is_int($k)) {
                // 'view'
                $action      = $v;
                $label       = static::humanizeAction($v);
                $description = null;
            } elseif (is_array($v)) {
                // 'view' => ['text' => '...', 'description' => '...']
                $action      = $k;
                $label       = $v['text'] ?? static::humanizeAction($k);
                $description = $v['description'] ?? null;
            } else {
                // 'view' => 'Kann anzeigen'
                $action      = $k;
                $label       = $v;
                $description = null;
            }

            $result[] = [
                'key'         => PagePermissionKeyBuilder::build(
                    entity: $pageClass,
                    affix: $action,
                    subject: $subject,
                    case: $case,
                    separator: $separator,
                ),
                // __(): see EnhancedResourcePermissionsForm::resolvePermissionsForResource()
                // for why literal labels/descriptions are routed through the translator.
                'label'       => __($label),
                'description' => $description !== null ? __($description) : null,
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Label helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the section title from the page's own display properties.
     * Checks navigationLabel → title → heading (instance default) in order,
     * so pages that only set $heading (e.g. wizard pages) still get a
     * meaningful label instead of the raw class name.
     */
    protected static function resolveSectionTitle(string $pageClass): string
    {
        $defaults = (new \ReflectionClass($pageClass))->getDefaultProperties();

        $title = $defaults['navigationLabel']
            ?? $defaults['title']
            ?? $defaults['heading']
            ?? Str::headline(class_basename($pageClass));

        return __($title);
    }

    /**
     * Returns the page slug as a human-readable hint in the section description.
     * Falls back to the FQCN if the slug cannot be resolved.
     */
    protected static function resolveSectionDescription(string $pageClass): string
    {
        try {
            return $pageClass::getSlug();
        } catch (\Throwable) {
            return $pageClass;
        }
    }

    protected static function humanizeAction(string $action): string
    {
        return Str::headline($action);
    }

    protected static function fieldName(string $pageClass): string
    {
        return 'page_permissions_' . Str::snake(class_basename($pageClass));
    }
}
