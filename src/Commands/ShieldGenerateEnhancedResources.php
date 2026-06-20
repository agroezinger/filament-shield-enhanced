<?php

namespace Agroezinger\FilamentShieldEnhanced\Commands;

use Agroezinger\FilamentShieldEnhanced\Support\ResourcePermissionKeyBuilder;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\select;

class ShieldGenerateEnhancedResources extends Command
{
    protected $signature = 'shield:generate-enhanced-resources
        {--panel= : Panel ID to scan (omit to select interactively)}
        {--all-panels : Scan all panels}';

    protected $description = 'Generate Spatie permissions for every action declared in getShieldResourcePermissions()';

    public function handle(): int
    {
        $panelId = $this->option('panel') ?: (
            $this->option('all-panels') ? null : select(
                label: 'Which panel do you want to scan?',
                options: collect(Filament::getPanels())->keys()->toArray(),
            )
        );

        $panels = $panelId
            ? [Filament::getPanel($panelId)]
            : Filament::getPanels();

        $separator = config('filament-shield.permissions.separator', ':');
        $case      = config('filament-shield.permissions.case', 'pascal');
        $permClass = app(PermissionRegistrar::class)->getPermissionClass();

        $created = 0;
        $skipped = 0;

        foreach ($panels as $panel) {
            Filament::setCurrentPanel($panel);

            foreach ($panel->getResources() as $resourceClass) {
                if (
                    ! is_subclass_of($resourceClass, Resource::class)
                    || ! method_exists($resourceClass, 'getShieldResourcePermissions')
                ) {
                    continue;
                }

                $actions = $resourceClass::getShieldResourcePermissions();
                $subject = class_basename($resourceClass::getModel());
                $guard   = $panel->getAuthGuard();

                foreach ($actions as $k => $v) {
                    $action = is_int($k) ? $v : $k;

                    $key = ResourcePermissionKeyBuilder::build(
                        entity: $resourceClass,
                        affix: $action,
                        subject: $subject,
                        case: $case,
                        separator: $separator,
                    );

                    $permission = $permClass::firstOrCreate(
                        ['name' => $key, 'guard_name' => $guard]
                    );

                    if ($permission->wasRecentlyCreated) {
                        $this->components->twoColumnDetail($key, '<fg=green>created</>');
                        $created++;
                    } else {
                        $this->components->twoColumnDetail($key, '<fg=gray>already exists</>');
                        $skipped++;
                    }
                }
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Permissions created', (string) $created);
        $this->components->twoColumnDetail('Already existed',     (string) $skipped);

        return self::SUCCESS;
    }
}
