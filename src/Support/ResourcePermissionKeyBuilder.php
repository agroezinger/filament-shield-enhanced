<?php

namespace Agroezinger\FilamentShieldEnhanced\Support;

use Illuminate\Support\Str;

/**
 * Builds the two-part permission key for resources that expose
 * getShieldResourcePermissions():
 *
 *   {Action}{sep}{ModelBasename}
 *   e.g. "ViewContactInfo:Member"
 *
 * No prefix is added — this matches filament-shield's own resource
 * permission format (ViewAny:Member, Create:Member, …) so custom
 * resource actions are visually consistent with CRUD permissions.
 * Separator and case are inherited from filament-shield's config.
 */
class ResourcePermissionKeyBuilder
{
    public static function build(
        string $entity,
        string $affix,
        string $subject,
        string $case,
        string $separator,
    ): string {
        $formattedAffix   = static::applyCase($affix, $case);
        $formattedSubject = static::applyCase($subject, $case);

        return $formattedAffix . $separator . $formattedSubject;
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
