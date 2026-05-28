<?php

namespace Agroezinger\FilamentShieldEnhanced;

use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedPagePermissionsForm;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Event;

class FilamentShieldEnhancedPlugin implements Plugin
{
    // -------------------------------------------------------------------------
    // Fluent constructor
    // -------------------------------------------------------------------------

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-shield-enhanced';
    }

    // -------------------------------------------------------------------------
    // Panel registration
    // -------------------------------------------------------------------------

    public function register(Panel $panel): void
    {
        // Nothing to register at the panel level — our work is done by hooking
        // into the filament-shield permission key builder (ServiceProvider) and
        // by providing the form builder helper (EnhancedPagePermissionsForm).
    }

    public function boot(Panel $panel): void
    {
        // Nothing extra to boot.
    }
}
