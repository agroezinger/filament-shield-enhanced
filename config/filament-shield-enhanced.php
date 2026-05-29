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
