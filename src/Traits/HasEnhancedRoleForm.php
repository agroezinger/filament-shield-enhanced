<?php

namespace Agroezinger\FilamentShieldEnhanced\Traits;

use Agroezinger\FilamentShieldEnhanced\Forms\EnhancedPagePermissionsForm;

/**
 * Add to the published EditRole page so that page-permission checkboxes are
 * pre-selected when the form opens.
 *
 * Usage:
 *
 *   class EditRole extends EditRecord
 *   {
 *       use HasEnhancedRoleForm;
 *       // … rest unchanged
 *   }
 */
trait HasEnhancedRoleForm
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $existing = $this->record->permissions->pluck('name')->all();

        foreach (EnhancedPagePermissionsForm::getPagePermissionFields() as $fieldName => $keys) {
            $data[$fieldName] = array_values(array_intersect($keys, $existing));
        }

        return $data;
    }
}
