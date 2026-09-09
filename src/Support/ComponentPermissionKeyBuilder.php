<?php

namespace Agroezinger\FilamentShieldEnhanced\Support;

use Illuminate\Support\Str;

/**
 * Builds the three-part permission key for arbitrary Livewire components that
 * expose getShieldComponentPermissions():
 *
 *   {Prefix}{sep}{Action}{sep}{Subject}
 *   e.g. "Component:Delete:CommentComponent"
 *
 * Same shape as PagePermissionKeyBuilder, but for components that aren't
 * registered with any Filament panel (Shield's own Page/Resource/Widget
 * discovery never sees them). The prefix is taken from the addon config key
 * `filament-shield-enhanced.components.permission_prefix` (default:
 * 'Component'). Separator and case are inherited from filament-shield's own
 * config so the keys are visually consistent with Page/Resource permissions.
 */
class ComponentPermissionKeyBuilder
{
    public static function build(
        string $entity,
        string $affix,
        string $subject,
        string $case,
        string $separator,
    ): string {
        $prefix = config('filament-shield-enhanced.components.permission_prefix', 'Component');

        $formattedAffix = static::applyCase($affix, $case);
        $formattedSubject = static::applyCase($subject, $case);
        $formattedPrefix = static::applyCase($prefix, $case);

        return implode($separator, [$formattedPrefix, $formattedAffix, $formattedSubject]);
    }

    protected static function applyCase(string $value, string $case): string
    {
        return match ($case) {
            'camel' => Str::camel($value),
            'kebab' => Str::kebab($value),
            'snake' => Str::snake($value),
            'upper_snake' => Str::upper(Str::snake($value)),
            default => Str::studly($value), // pascal
        };
    }
}
