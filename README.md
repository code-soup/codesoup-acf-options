
# CodeSoup ACF Options

Composer package for managing WordPress options pages using ACF and custom post type. Data is stored and retrieved from  `post_content` field and does not use the wp_options table. Supports multiple instances and capability-based access control.

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

1. Download the plugin as a ZIP file from GitHub:
   - Go to https://github.com/codesoup/acf-options
   - Click the green "Code" button
   - Select "Download ZIP"

2. Extract the ZIP file and rename the folder to `codesoup-acf-options`

3. Move the folder to your WordPress plugins directory:

4. Activate the plugin through the WordPress admin panel


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

#### Example 2: Retrieve existing instance from different file

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
    )
);
```

## ACF Field Groups

Assign field groups using the ACF Options location rule.

## API

**Static Methods**
-   `create( string $instance_key, array $config = [] ): Manager`
-   `get( string $instance_key ): ?Manager`
-   `get_all(): array`
-   `destroy( string $instance_key ): bool`

**Instance Methods**
-   `register_page( array $args ): void`
-   `init(): void`
-   `get_options( string $page_id ): array`

## License

[GPLv3](https://www.gnu.org/licenses/gpl-3.0.txt)
