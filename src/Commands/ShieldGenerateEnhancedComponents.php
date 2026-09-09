<?php

namespace Agroezinger\FilamentShieldEnhanced\Commands;

use Agroezinger\FilamentShieldEnhanced\Support\ComponentDiscovery;
use Agroezinger\FilamentShieldEnhanced\Support\ComponentPermissionKeyBuilder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class ShieldGenerateEnhancedComponents extends Command
{
    protected $signature = 'shield:generate-enhanced-components
        {--guard= : Guard name to generate permissions for (defaults to config("auth.defaults.guard"))}';

    protected $description = 'Generate Spatie permissions for every action declared in getShieldComponentPermissions()';

    public function handle(): int
    {
        $guard = $this->option('guard') ?: config('auth.defaults.guard', 'web');
        $separator = config('filament-shield.permissions.separator', ':');
        $case = config('filament-shield.permissions.case', 'pascal');
        $permClass = app(PermissionRegistrar::class)->getPermissionClass();

        $created = 0;
        $skipped = 0;

        foreach (ComponentDiscovery::discover() as $componentClass) {
            $actions = $componentClass::getShieldComponentPermissions();
            $subject = class_basename($componentClass);

            foreach ($actions as $k => $v) {
                $action = is_int($k) ? $v : $k;

                $key = ComponentPermissionKeyBuilder::build(
                    entity: $componentClass,
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

        $this->newLine();
        $this->components->twoColumnDetail('Permissions created', (string) $created);
        $this->components->twoColumnDetail('Already existed', (string) $skipped);

        return self::SUCCESS;
    }
}
