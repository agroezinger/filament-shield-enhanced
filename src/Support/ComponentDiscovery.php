<?php

namespace Agroezinger\FilamentShieldEnhanced\Support;

use Agroezinger\FilamentShieldEnhanced\Traits\HasComponentShield;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Discovers classes that use HasComponentShield.
 *
 * Unlike Pages, Resources and Widgets, arbitrary Livewire components are not
 * registered with any Filament panel — there is no built-in registry to scan.
 * Instead we walk a configured directory (default: app/Livewire) and check
 * every class file found there for the trait.
 *
 * @internal Not part of the public addon API — may change between minor
 *           versions. Depend on HasComponentShield, ShieldGenerateEnhancedComponents
 *           or EnhancedComponentPermissionsForm instead.
 */
class ComponentDiscovery
{
    /**
     * @return Collection<int, class-string> Fully-qualified class names of every
     *                                       discovered class using HasComponentShield.
     */
    public static function discover(): Collection
    {
        $paths = static::scanPaths();

        $classes = collect();

        foreach ($paths as $path => $namespace) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = $file->getRelativePathname();
                $class = $namespace.'\\'.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    $relative
                );

                if (! class_exists($class)) {
                    continue;
                }

                if (in_array(HasComponentShield::class, class_uses_recursive($class), true)) {
                    $classes->push($class);
                }
            }
        }

        return $classes->unique()->values();
    }

    /**
     * @return array<string, string> Absolute directory path => base namespace
     */
    protected static function scanPaths(): array
    {
        $configured = config('filament-shield-enhanced.components.scan_paths', [
            app_path('Livewire') => 'App\\Livewire',
        ]);

        return $configured;
    }
}
