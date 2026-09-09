<?php

namespace Agroezinger\FilamentShieldEnhanced\Support;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Contracts\HasLabel;
use UnitEnum;

/**
 * Resolves a Resource's or Page's navigation group to a plain display string,
 * and the display order of those groups, so permission forms can cluster
 * entries the same way the panel's own sidebar does.
 */
class NavigationGroupResolver
{
    /**
     * Resolves a Resource/Page class's navigation group to a plain string.
     * Classes without a group (null) resolve to '', the caller's signal to
     * bucket them into a catch-all group instead.
     */
    public static function labelFor(string $class): string
    {
        if (! method_exists($class, 'getNavigationGroup')) {
            return '';
        }

        $group = $class::getNavigationGroup();

        if ($group instanceof UnitEnum) {
            return $group instanceof HasLabel ? (string) $group->getLabel() : $group->name;
        }

        return (string) ($group ?? '');
    }

    /**
     * Resolves the display order of navigation groups from the current
     * panel's own ->navigationGroups() declaration, so permission tabs follow
     * the same order as the sidebar instead of an arbitrary one.
     *
     * Returns the RAW, untranslated group identifier for each entry — the
     * same value labelFor() returns for an individual Resource/Page — not
     * its (possibly translated) display label. A panel that registers
     * groups with explicit string array keys (e.g. so it can wrap the label
     * in a Closure/__() for i18n while keeping a stable grouping key, see
     * ClubPanelProvider) has that raw key read directly; a plain positional
     * list (no explicit keys) falls back to the group's label, matching the
     * pre-i18n behaviour.
     *
     * @return list<string>
     */
    public static function order(): array
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getCurrentOrDefaultPanel();

        if (! $panel) {
            return [];
        }

        return collect($panel->getNavigationGroups())
            ->map(fn (NavigationGroup|string $group, string|int $key): string => is_string($key)
                ? $key
                : ($group instanceof NavigationGroup ? (string) $group->getLabel() : $group))
            ->filter(fn (string $label): bool => $label !== '')
            ->values()
            ->all();
    }
}
