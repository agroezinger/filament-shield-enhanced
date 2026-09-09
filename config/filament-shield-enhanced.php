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

        /*
        |----------------------------------------------------------------------
        | Group permissions by navigation group
        |----------------------------------------------------------------------
        |
        | When true, a grouped Role permission form (e.g. RoleResource's
        | getShieldFormComponents()) clusters Resources/Pages first by their
        | Filament navigation group (matching the sidebar), with a second-level
        | "category" tab (Resources/Pages/…) underneath. When false, those
        | category tabs sit directly at the top level instead, each showing
        | every entry of that type in one flat list, ungrouped.
        |
        */

        'group_by_navigation' => true,

        /*
        |----------------------------------------------------------------------
        | Navigation group sort order
        |----------------------------------------------------------------------
        |
        | Only relevant when group_by_navigation is true. 'navigation' orders
        | the group tabs the same way the panel declares them via
        | ->navigationGroups() (matching the sidebar). 'alphabetical' sorts
        | group labels alphabetically instead.
        |
        */

        'group_sort' => 'navigation', // 'navigation' | 'alphabetical'

        /*
        |----------------------------------------------------------------------
        | Category labels
        |----------------------------------------------------------------------
        |
        | End users configuring roles don't know what a Filament "Resource" or
        | "Page" is — these labels describe each permission category by what
        | it lets someone DO rather than by the underlying Filament concept.
        | Override per app to match your own terminology.
        |
        */

        'labels' => [
            'resources'  => 'Ressourcen',
            'pages'      => 'Seiten',
            'widgets'    => 'Widgets',
            'custom'     => 'Sonstige Berechtigungen',
            'misc_group' => 'Sonstiges',
        ],
    ],

];
