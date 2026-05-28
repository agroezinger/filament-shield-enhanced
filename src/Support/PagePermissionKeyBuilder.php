<?php

namespace Agroezinger\FilamentShieldEnhanced\Support;

use Illuminate\Support\Str;

/**
 * Builds the three-part permission key for pages that expose
 * getShieldPagePermissions():
 *
 *   {Prefix}{sep}{Action}{sep}{Subject}
 *   e.g. "Page:EditSettings:SamplePageName"
 *
 * The prefix is taken from the addon config key
 * `filament-shield-enhanced.pages.permission_prefix` (default: 'Page').
 * The separator and case are inherited from filament-shield's config so the
 * keys are visually consistent with Resource permissions.
 */
class PagePermissionKeyBuilder
{
    public static function build(
        string $entity,
        string $affix,
        string $subject,
        string $case,
        string $separator,
    ): string {
        $prefix = config('filament-shield-enhanced.pages.permission_prefix', 'Page');

        $formattedAffix = static::applyCase($affix, $case);
        $formattedSubject = static::applyCase($subject, $case);
        $formattedPrefix = static::applyCase($prefix, $case);

        return implode($separator, [$formattedPrefix, $formattedAffix, $formattedSubject]);
    }

    protected static function applyCase(string $value, string $case): string
    {
        return match ($case) {
            'camel'       => Str::camel($value),
            'kebab'       => Str::kebab($value),
            'snake'       => Str::snake($value),
            'upper_snake' => Str::upper(Str::snake($value)),
            default       => Str::studly($value), // pascal
        };
    }
}
