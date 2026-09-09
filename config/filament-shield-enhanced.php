<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page Permission Prefix
    |--------------------------------------------------------------------------
    |
    | The first segment of the three-part permission key for fine-grained page
    | permissions:
    |
    |   {prefix}{separator}{action}{separator}{subject}
    |   e.g. "Page:EditSettings:SamplePageName"
    |
    | The separator and case are read from filament-shield's own config so all
    | keys look consistent.
    |
    */

    'pages' => [
        'permission_prefix' => 'Page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Component Permission Prefix & Discovery
    |--------------------------------------------------------------------------
    |
    | Same three-part key format as pages, but for arbitrary Livewire
    | components (HasComponentShield) that aren't registered with any
    | Filament panel:
    |
    |   {prefix}{separator}{action}{separator}{subject}
    |   e.g. "Component:Delete:CommentComponent"
    |
    | Since there's no panel registry to scan, `scan_paths` lists directories
    | to walk for classes using HasComponentShield, each mapped to its base
    | namespace. Add more entries here if components live outside app/Livewire.
    |
    */

    'components' => [
        'permission_prefix' => 'Component',

        'scan_paths' => [
            app_path('Livewire') => 'App\\Livewire',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Layout
    |--------------------------------------------------------------------------
    |
    | Column configuration for the EnhancedPagePermissionsForm builder. These
    | values are passed directly to Filament's Grid and CheckboxList columns().
    |
    */

    'ui' => [
        'grid_columns' => [
            'default' => 1,
            'sm' => 2,
            'lg' => 2,
        ],

        'checkbox_list_columns' => [
            'default' => 1,
            'sm' => 2,
        ],
    ],

];
