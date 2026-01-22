<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */
    'guards' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    | Role name that bypasses ALL permission checks via Gate::before.
    */
    'super_admin_role' => 'super_admin',

    /*
    |--------------------------------------------------------------------------
    | Panel User Role
    |--------------------------------------------------------------------------
    | Baseline role automatically assigned to new users for basic panel access.
    */
    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    | Support for 'orders.*' to match 'orders.view', etc. UNIQUE to Authz.
    */
    'wildcard_permissions' => true,

    /*
    |--------------------------------------------------------------------------
    | Authz Scopes
    |--------------------------------------------------------------------------
    */
    'scoped_to_tenant' => true,

    'central_app' => true,

    'authz_scopes' => [
        'enabled' => true,
        'auto_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Key Builder
    |--------------------------------------------------------------------------
    | case: snake, kebab, camel, pascal, upper_snake, lower
    */
    'permissions' => [
        'separator' => '.',
        'case' => 'kebab',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'subject' => 'model',
        'actions' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */
    'pages' => [
        'prefix' => 'page',
        'exclude' => [
            \Filament\Pages\Dashboard::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'prefix' => 'widget',
        'exclude' => [
            \Filament\Widgets\AccountWidget::class,
            \Filament\Widgets\FilamentInfoWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    | Additional permissions beyond resources/pages/widgets.
    | Examples: 'approve_posts', 'export_data' => 'Export Data'
    */
    'custom_permissions' => [
        'institution.manage-members' => 'Manage Institution Members',
        'institution.manage-donation-accounts' => 'Manage Donation Accounts',
        'speaker.manage-members' => 'Manage Speaker Members',
        'event.view-registrations' => 'View Event Registrations',
        'event.export-registrations' => 'Export Event Registrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    */
    'policies' => [
        'path' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Settings',
        'sort' => 99,
        'icons' => [
            'roles' => 'heroicon-o-shield-check',
            'permissions' => 'heroicon-o-key',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Resource UI
    |--------------------------------------------------------------------------
    */
    'role_resource' => [
        'slug' => 'authz/roles',
        'tabs' => [
            'resources' => true,
            'pages' => true,
            'widgets' => true,
            'custom_permissions' => true,
        ],
        'grid_columns' => 2,
        'checkbox_columns' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'permissions' => [],
        'roles' => [],
    ],
];
