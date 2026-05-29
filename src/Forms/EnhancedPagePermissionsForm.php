<?php

namespace Agroezinger\FilamentShieldEnhanced\Forms;

use Agroezinger\FilamentShieldEnhanced\Support\PagePermissionKeyBuilder;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Pages\BasePage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * EnhancedPagePermissionsForm
 *
 * Builds the Filament form components for managing fine-grained page
 * permissions inside a published RoleResource. Each Page that declares
 * getShieldPagePermissions() gets its own Section with individual checkboxes.
 *
 * ---
 * Usage in a published RoleResource form schema:
 *
 *   use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedPagePermissionsForm;
 *   use Filament\Forms\Components\Tabs;
 *
 *   Tabs\Tab::make(__('filament-shield::filament-shield.resources.tabs.pages'))
 *       ->schema(EnhancedPagePermissionsForm::make()),
 * ---
 */
class EnhancedPagePermissionsForm
{
    /**
     * Returns a map of CheckboxList field name => list of permission keys for
     * every enhanced Page. Used to pre-fill the form in EditRole::mutateFormDataBeforeFill().
     *
     * @return array<string, list<string>>
     */
    public static function getPagePermissionFields(): array
    {
        $result = [];

        foreach (static::discoverEnhancedPages() as $page) {
            $fieldName          = 'page_permissions_' . Str::snake(class_basename($page['class']));
            $result[$fieldName] = array_column($page['permissions'], 'key');
        }

        return $result;
    }

    /**
     * Returns an array of Filament form components (Grid > Sections > CheckboxLists)
     * for every enhanced Page discovered in the application.
     *
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function make(): array
    {
        $pages = static::discoverEnhancedPages();

        if ($pages->isEmpty()) {
            return [];
        }

        $gridColumns = config('filament-shield-enhanced.ui.grid_columns', [
            'default' => 1,
            'sm' => 2,
            'lg' => 3,
        ]);

        $sections = $pages->map(fn(array $page) => static::buildSection($page))->all();

        return [
            Grid::make($gridColumns)->schema($sections),
        ];
    }

    // -------------------------------------------------------------------------
    // Section builder
    // -------------------------------------------------------------------------

    protected static function buildSection(array $page): Section
    {
        /** @var class-string<BasePage> $pageClass */
        $pageClass = $page['class'];
        $permissions = $page['permissions']; // [['key' => '...', 'label' => '...'], …]

        $title = static::resolveSectionTitle($pageClass);

        $options = collect($permissions)
            ->mapWithKeys(fn(array $perm) => [$perm['key'] => $perm['label']])
            ->all();

        $descriptions = collect($permissions)
            ->filter(fn(array $perm) => filled($perm['description']))
            ->mapWithKeys(fn(array $perm) => [$perm['key'] => $perm['description']])
            ->all();

        $checkboxListColumns = config('filament-shield-enhanced.ui.checkbox_list_columns', [
            'default' => 1,
            'sm' => 2,
        ]);

        $checkboxList = CheckboxList::make('page_permissions_' . Str::snake(class_basename($pageClass)))
            ->label('')
            ->options($options)
            ->columns($checkboxListColumns)
            ->gridDirection('row')
            ->bulkToggleable();

        if (!empty($descriptions)) {
            $checkboxList->descriptions($descriptions);
        }

        return Section::make($title)
            ->description(static::resolveSectionDescription($pageClass))
            ->compact()
            ->schema([$checkboxList]);
    }

    // -------------------------------------------------------------------------
    // Discovery
    // -------------------------------------------------------------------------

    /**
     * Discovers all Filament Page classes that declare getShieldPagePermissions()
     * and resolves their permission keys + labels.
     *
     * @return Collection<int, array{class: class-string, permissions: list<array{key: string, label: string}>}>
     */
    protected static function discoverEnhancedPages(): Collection
    {
        $allPages = collect();

        try {
            $panels = \Filament\Facades\Filament::getPanels();

            foreach ($panels as $panel) {
                foreach ($panel->getPages() as $pageClass) {
                    if (
                        is_subclass_of($pageClass, BasePage::class)
                        && method_exists($pageClass, 'getShieldPagePermissions')
                    ) {
                        $allPages->push($pageClass);
                    }
                }
            }
        } catch (\Throwable) {
            // Outside of a panel context (e.g. during unit testing).
        }

        return $allPages
            ->unique()
            ->map(fn(string $class) => [
                'class' => $class,
                'permissions' => static::resolvePermissionsForPage($class),
            ])
            ->filter(fn(array $page) => !empty($page['permissions']))
            ->values();
    }

    /**
     * Resolve permission key + human-readable label for each action declared
     * by the given Page class.
     *
     * @param  class-string  $pageClass
     * @return list<array{key: string, label: string}>
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
                'label'       => $label,
                'description' => $description,
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

        return $defaults['navigationLabel']
            ?? $defaults['title']
            ?? $defaults['heading']
            ?? Str::headline(class_basename($pageClass));
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
}
