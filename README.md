
# CodeSoup ACF Options

Composer package for managing WordPress options pages using ACF and custom post type. Data is stored and retrieved from  `post_content` field and does not use the wp_options table. Supports multiple instances and capability-based access control.

This approach leverages native WordPress post locking to prevent concurrent edits and provides optionial revision history for all option changes.

In case you use custom capabilities make sure you create and assign capability to Administrator role otherwise options page will not be visible/accessible. You can use plugin like [User Role Editor](https://wordpress.org/plugins/user-role-editor/) to manage custom capabilites and roles.


## Requirements

-   PHP >= 8.1
-   WordPress >= 6.0
-   Advanced Custom Fields

## Installation

### Via Composer

```bash
composer require codesoup/acf-options
```

### As WordPress Plugin from GitHub

1. Download the plugin as a ZIP file from GitHub

2. Extract the ZIP file to your WordPress plugins directory

3. Activate the plugin

4. Add required create/register_page code to your functions.php


## Usage

#### Example 1: Create and retrieve in same file

```php
use CodeSoup\ACFOptions\Manager;

$manager = Manager::create( 'my_instance_key' );

$manager->register_page(
    array(
        'id'         => 'general',
        'title'      => 'General Settings',
        'capability' => 'manage_options',
    )
);

$manager->init();

// Retrieve options
$options = $manager->get_options( 'general' );
```

#### Example 2: Register multiple pages at once

```php
use CodeSoup\ACFOptions\Manager;

$manager = Manager::create( 'my_instance_key' );

$manager->register_pages(
    array(
        array(
            'id'         => 'general',
            'title'      => 'General Settings',
            'capability' => 'manage_options',
        ),
        array(
            'id'         => 'footer',
            'title'      => 'Footer Settings',
            'capability' => 'manage_options',
        ),
    )
);

$manager->init();
```

#### Example 3: Retrieve existing instance from different file

```php
// In functions.php or plugin init
$manager = \CodeSoup\ACFOptions\Manager::create( 'my_instance_key' );
$manager->register_page(
    array(
        'id'         => 'general',
        'title'      => 'General Settings',
        'capability' => 'manage_footer_options', // Custom wp_capability
    )
);
$manager->init();

// In template file eg: header.php
$manager = \CodeSoup\ACFOptions\Manager::get( 'my_instance_key' );
$options = $manager->get_options( 'general' );
```

#### Example 4: Retrieve single option value

```php
$manager = \CodeSoup\ACFOptions\Manager::get( 'my_instance_key' );

// Get single field from postmeta
$site_title = $manager->get_option( 'general', 'site_title' );

// With default value
$footer_text = $manager->get_option( 'footer', 'copyright_text', 'Default copyright' );
```

#### Example 5: Debug instance state

```php
// Get complete state information for debugging
$debug = \CodeSoup\ACFOptions\Manager::debug( 'my_instance_key' );

// Returns array with:
// - instance_key
// - config (all configuration options)
// - pages (all registered pages with their current values)

print_r( $debug );
```


## Configuration

Available options with defaults:

```php
$manager = Manager::create(
    'my_instance_key',
    array(
        'post_type'     => 'codesoup_options',         // {my_instance_key}_options
        'prefix'        => 'codesoup-',                // {my_instance_key}-options-
        'menu_position' => 50,                         // 99
        'menu_icon'     => 'dashicons-admin-settings', // dashicons-admin-generic
        'menu_label'    => 'Site Options',             // ACF Options
        'revisions'     => true,                       // false
    )
);
```

## ACF Field Groups

Assign field groups using the ACF Options location rule.

## Migration

Use the `migrate()` method to handle configuration changes or sync capabilities:

```php
// Migrate from old config to new config
$results = \CodeSoup\ACFOptions\Manager::migrate(
    'my_instance_key',
    array(
        'post_type' => 'old_options',      // Old post type
        'prefix'    => 'old-prefix-',      // Old prefix
    ),
    array(
        array(
            'id'         => 'general',
            'capability' => 'manage_options',  // Updated capability
        ),
        array(
            'id'         => 'footer',
            'capability' => 'manage_footer',   // Updated capability
        ),
    )
);

// Check results
print_r( $results );
```

The migration handles:
- Changing `post_type` (updates all posts)
- Changing `prefix` (renames all post slugs)
- Syncing capabilities from code to existing posts

## API

**Static Methods**
-   `create( string $instance_key, array $config = [] ): Manager`
-   `get( string $instance_key ): ?Manager`
-   `get_all(): array`
-   `destroy( string $instance_key ): bool`
-   `debug( string $instance_key ): array`
-   `migrate( string $instance_key, array $old_config, array $new_pages = [] ): array`

**Instance Methods**
-   `register_page( array $args ): void`
-   `register_pages( array $pages ): void`
-   `init(): void`
-   `get_options( string $page_id ): array`
-   `get_option( string $page_id, string $field_name, mixed $default = null ): mixed`

## Issues

Report issues at: https://github.com/code-soup/codesoup-acf-options/issues

## License

This project is licensed under the [GPLv3](https://www.gnu.org/licenses/gpl-3.0.txt).

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
