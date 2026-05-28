<?php

namespace Agroezinger\FilamentShieldEnhanced\Forms;

use Agroezinger\FilamentShieldEnhanced\Support\PagePermissionKeyBuilder;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
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
            'sm'      => 2,
            'lg'      => 3,
        ]);

        $sections = $pages->map(fn (array $page) => static::buildSection($page))->all();

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
        $pageClass   = $page['class'];
        $permissions = $page['permissions']; // [['key' => '...', 'label' => '...'], …]

        $title = static::resolveSectionTitle($pageClass);

        $options = collect($permissions)
            ->mapWithKeys(fn (array $perm) => [$perm['key'] => $perm['label']])
            ->all();

        $checkboxListColumns = config('filament-shield-enhanced.ui.checkbox_list_columns', [
            'default' => 1,
            'sm'      => 2,
        ]);

        return Section::make($title)
            ->description($pageClass) // FQCN as developer hint
            ->compact()
            ->schema([
                CheckboxList::make('page_permissions_' . Str::snake(class_basename($pageClass)))
                    ->label('')
                    ->options($options)
                    ->columns($checkboxListColumns)
                    ->gridDirection('row')
                    ->bulkToggleable(),
            ]);
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
            ->map(fn (string $class) => [
                'class'       => $class,
                'permissions' => static::resolvePermissionsForPage($class),
            ])
            ->filter(fn (array $page) => ! empty($page['permissions']))
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
        /** @var list<string> $actions */
        $actions   = $pageClass::getShieldPagePermissions();
        $subject   = class_basename($pageClass);
        $separator = config('filament-shield.permissions.separator', ':');
        $case      = config('filament-shield.permissions.case', 'pascal');

        return array_map(fn (string $action) => [
            'key'   => PagePermissionKeyBuilder::build(
                entity: $pageClass,
                affix: $action,
                subject: $subject,
                case: $case,
                separator: $separator,
            ),
            'label' => static::humanizeAction($action),
        ], $actions);
    }

    // -------------------------------------------------------------------------
    // Label helpers
    // -------------------------------------------------------------------------

    protected static function resolveSectionTitle(string $pageClass): string
    {
        if (method_exists($pageClass, 'getTitle')) {
            try {
                return $pageClass::getTitle();
            } catch (\Throwable) {
                // Static call may need a panel context.
            }
        }

        return Str::headline(class_basename($pageClass));
    }

    protected static function humanizeAction(string $action): string
    {
        return Str::headline($action);
    }
}
