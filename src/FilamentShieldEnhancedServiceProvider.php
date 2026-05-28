<?php

namespace Agroezinger\FilamentShieldEnhanced;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentShieldEnhancedServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-shield-enhanced')
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        $this->registerPagePermissionHook();
    }

    /**
     * Register the permission-key customisation for pages that define
     * getShieldPagePermissions(). All non-Page entities and Pages that do
     * NOT define the method are handled exactly as before by the original
     * filament-shield package.
     *
     * filament-shield's buildPermissionKeyUsing() registers a single global
     * callback. We cannot chain to a "previous" callback through the public
     * API, so we intercept only the cases we own and delegate everything else
     * to defaultPermissionKeyBuilder().
     *
     * If the application's AppServiceProvider also calls buildPermissionKeyUsing()
     * it should do so AFTER this addon is booted (i.e. in AppServiceProvider::boot())
     * and should itself fall through to the default builder for entities it does
     * not recognise — exactly the same pattern.
     */
    protected function registerPagePermissionHook(): void
    {
        \BezhanSalleh\FilamentShield\Facades\FilamentShield::buildPermissionKeyUsing(
            function (
                string $entity,
                string $affix,
                string $subject,
                string $case,
                string $separator
            ): string {
                // Intercept only Page classes that declare fine-grained permissions.
                if (
                    is_subclass_of($entity, \Filament\Pages\BasePage::class)
                    && method_exists($entity, 'getShieldPagePermissions')
                ) {
                    return \Agroezinger\FilamentShieldEnhanced\Support\PagePermissionKeyBuilder::build(
                        entity: $entity,
                        affix: $affix,
                        subject: $subject,
                        case: $case,
                        separator: $separator,
                    );
                }

                // All other entities → filament-shield's built-in default.
                return \BezhanSalleh\FilamentShield\Facades\FilamentShield::defaultPermissionKeyBuilder(
                    affix: $affix,
                    separator: $separator,
                    subject: $subject,
                    case: $case,
                );
            }
        );
    }
}
