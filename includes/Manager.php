<?php
/**
 * ACF Options Manager
 *
 * @package CodeSoup\ACFOptions
 */

namespace CodeSoup\ACFOptions;

// Don't allow direct access to file.
defined( 'ABSPATH' ) || die;

/**
 * Manager class for ACF Options
 *
 * Manages ACF options pages using custom post types with instance key support.
 * Supports multiple instances with different instance keys.
 *
 * @since 1.0.0
 */
class Manager {

	/**
	 * Registry of all Manager instances
	 *
	 * @var array<string, Manager>
	 */
	private static array $instances = array();

	/**
	 * Instance identifier
	 *
	 * @var string
	 */
	private string $instance_key;

	/**
	 * Configuration options
	 *
	 * @var array
	 */
	private array $config;

	/**
	 * Registered pages
	 *
	 * @var array<Page>
	 */
	private array $pages = array();

	/**
	 * Whether hooks have been registered
	 *
	 * @var bool
	 */
	private bool $hooks_registered = false;

	/**
	 * Whether ACF location type has been registered globally
	 *
	 * @var bool
	 */
	private static bool $location_type_registered = false;

	/**
	 * Page creation errors
	 *
	 * @var array
	 */
	private array $creation_errors = array();

	/**
	 * Cache of created page IDs to avoid repeated existence checks
	 *
	 * @var array
	 */
	private array $created_pages = array();

	/**
	 * Create a new Manager instance
	 *
	 * @param string $instance_key Unique instance identifier.
	 * @param array  $config Configuration options.
	 * @return Manager
	 */
	public static function create( string $instance_key, array $config = array() ): Manager {
		if ( isset( self::$instances[ $instance_key ] ) ) {
			return self::$instances[ $instance_key ];
		}

		$instance                         = new self( $instance_key, $config );
		self::$instances[ $instance_key ] = $instance;

		return $instance;
	}

	/**
	 * Get an existing Manager instance
	 *
	 * @param string $instance_key Instance identifier.
	 * @return Manager|null
	 */
	public static function get( string $instance_key ): ?Manager {
		return self::$instances[ $instance_key ] ?? null;
	}

	/**
	 * Get all Manager instances
	 *
	 * @return array<string, Manager>
	 */
	public static function get_all(): array {
		return self::$instances;
	}

	/**
	 * Destroy a Manager instance
	 *
	 * @param string $instance_key Instance identifier.
	 * @return bool True if instance was destroyed, false if not found.
	 */
	public static function destroy( string $instance_key ): bool {
		if ( ! isset( self::$instances[ $instance_key ] ) ) {
			return false;
		}

		unset( self::$instances[ $instance_key ] );
		return true;
	}

	/**
	 * Debug instance - get complete state information
	 *
	 * Returns configuration, registered pages, and current values for all pages.
	 *
	 * @param string $instance_key Instance identifier.
	 * @return array Debug information or error.
	 */
	public static function debug( string $instance_key ): array {
		$instance = self::get( $instance_key );

		if ( ! $instance ) {
			return array(
				'success' => false,
				'error'   => 'Instance not found',
			);
		}

		$config = $instance->get_config();
		$pages  = $instance->get_pages();

		$debug_info = array(
			'success'      => true,
			'instance_key' => $instance_key,
			'config'       => $config,
			'pages'        => array(),
		);

		foreach ( $pages as $page ) {
			$post_name = $config['prefix'] . $page->id;

			$page_data = array(
				'id'          => $page->id,
				'title'       => $page->title,
				'capability'  => $page->capability,
				'description' => $page->description,
				'post_name'   => $post_name,
				'values'      => $instance->get_options( $page->id ),
			);

			$debug_info['pages'][] = $page_data;
		}

		return $debug_info;
	}

	/**
	 * Migrate instance configuration
	 *
	 * Handles:
	 * - Changing post_type or prefix (renames posts)
	 * - Syncing capabilities from code to existing posts
	 *
	 * @param string $instance_key Instance identifier.
	 * @param array  $old_config Old configuration with 'post_type' and 'prefix'.
	 * @param array  $new_pages Array of new page definitions with updated capabilities.
	 * @return array Migration results with counts.
	 */
	public static function migrate( string $instance_key, array $old_config, array $new_pages = array() ): array {
		global $wpdb;

		$instance = self::get( $instance_key );
		if ( ! $instance ) {
			return array(
				'success' => false,
				'error'   => 'Instance not found',
			);
		}

		$new_config    = $instance->get_config();
		$old_post_type = $old_config['post_type'] ?? null;
		$old_prefix    = $old_config['prefix'] ?? null;
		$new_post_type = $new_config['post_type'];
		$new_prefix    = $new_config['prefix'];

		$results = array(
			'success'              => true,
			'posts_updated'        => 0,
			'post_type_changed'    => 0,
			'prefix_changed'       => 0,
			'capabilities_synced'  => 0,
			'errors'               => array(),
		);

		// Get all posts with old post_type.
		$posts = get_posts(
			array(
				'post_type'      => $old_post_type ?? $new_post_type,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			)
		);

		if ( empty( $posts ) ) {
			$results['error'] = 'No posts found to migrate';
			return $results;
		}

		// Build capability map from new pages.
		$capability_map = array();
		foreach ( $new_pages as $page_args ) {
			if ( isset( $page_args['id'] ) && isset( $page_args['capability'] ) ) {
				$capability_map[ $page_args['id'] ] = $page_args['capability'];
			}
		}

		foreach ( $posts as $post ) {
			$updated = false;
			$post_id = $post->ID;

			// Update post_type if changed.
			if ( $old_post_type && $old_post_type !== $new_post_type ) {
				set_post_type( $post_id, $new_post_type );
				$results['post_type_changed']++;
				$updated = true;
			}

			// Update post_name (prefix) if changed.
			if ( $old_prefix && $old_prefix !== $new_prefix ) {
				$old_name = $post->post_name;
				if ( strpos( $old_name, $old_prefix ) === 0 ) {
					$page_id  = substr( $old_name, strlen( $old_prefix ) );
					$new_name = $new_prefix . $page_id;

					wp_update_post(
						array(
							'ID'        => $post_id,
							'post_name' => $new_name,
						)
					);
					$results['prefix_changed']++;
					$updated = true;

					// Clear cache with old key.
					$old_cache_key = 'options_' . $instance_key . '_' . $page_id;
					wp_cache_delete( $old_cache_key, 'acf_options' );
				}
			}

			// Sync capability if provided in new_pages.
			if ( ! empty( $capability_map ) ) {
				$post_name = get_post_field( 'post_name', $post_id );
				$prefix    = $new_prefix;

				if ( strpos( $post_name, $prefix ) === 0 ) {
					$page_id = substr( $post_name, strlen( $prefix ) );

					if ( isset( $capability_map[ $page_id ] ) ) {
						$new_capability = $capability_map[ $page_id ];
						$old_capability = get_post_meta( $post_id, '_acf_options_capability', true );

						if ( $old_capability !== $new_capability ) {
							update_post_meta( $post_id, '_acf_options_capability', $new_capability );
							$results['capabilities_synced']++;
							$updated = true;
						}
					}
				}
			}

			if ( $updated ) {
				$results['posts_updated']++;
			}
		}

		return $results;
	}

	/**
	 * Private constructor
	 *
	 * @param string $instance_key Unique instance identifier.
	 * @param array  $config Configuration options.
	 * @throws InvalidArgumentException If config validation fails.
	 */
	private function __construct( string $instance_key, array $config ) {
		$this->instance_key = $instance_key;
		$this->config       = array_merge(
			array(
				'post_type'     => $instance_key . '_options',
				'prefix'        => $instance_key . '-options-',
				'menu_position' => 99,
				'menu_icon'     => 'dashicons-admin-generic',
				'text_domain'   => 'codesoup-acf-options',
				'menu_label'    => 'Codesoup ACF Options',
				'revisions'     => false,
			),
			$config
		);

		$this->validate_config();
	}

	/**
	 * Validate configuration options
	 *
	 * @return void
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function validate_config(): void {
		// Validate menu_position is numeric.
		if ( isset( $this->config['menu_position'] ) && ! is_numeric( $this->config['menu_position'] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Config "menu_position" must be numeric, "%s" given.', esc_html( gettype( $this->config['menu_position'] ) ) )
			);
		}

		// Validate menu_icon starts with dashicons- or is a valid URL/base64.
		if ( isset( $this->config['menu_icon'] ) ) {
			$icon = $this->config['menu_icon'];
			if ( ! empty( $icon ) &&
				strpos( $icon, 'dashicons-' ) !== 0 &&
				strpos( $icon, 'data:image' ) !== 0 &&
				! filter_var( $icon, FILTER_VALIDATE_URL ) ) {
				throw new \InvalidArgumentException(
					'Config "menu_icon" must be a dashicon class, data URI, or valid URL.'
				);
			}
		}
	}

	/**
	 * Register a new options page
	 *
	 * @param array $args Page arguments.
	 * @return void
	 */
	public function register_page( array $args ): void {
		$page          = new Page( $args );
		$this->pages[] = $page;
	}

	/**
	 * Register multiple options pages
	 *
	 * @param array $pages Array of page arguments.
	 * @return void
	 */
	public function register_pages( array $pages ): void {
		foreach ( $pages as $page_args ) {
			$this->register_page( $page_args );
		}
	}

	/**
	 * Initialize the manager
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		if ( $this->hooks_registered ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		// Check if ACF is available.
		if ( ! function_exists( 'acf' ) ) {
			add_action( 'admin_notices', array( $this, 'show_acf_missing_notice' ) );
			$this->hooks_registered = true;
			return;
		}

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'current_screen', array( $this, 'maybe_ensure_pages_exist' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'show_creation_errors' ) );
		add_action( 'acf/save_post', array( $this, 'save_options' ), 20 );
		add_filter( 'pre_get_posts', array( $this, 'filter_posts_by_capability' ) );
		add_filter( "views_edit-{$this->config['post_type']}", '__return_empty_array' );
		add_filter( "manage_{$this->config['post_type']}_posts_columns", array( $this, 'remove_date_column' ) );
		add_filter( 'post_row_actions', array( $this, 'remove_row_actions' ), 10, 2 );

		// Register ACF location type (only once globally).
		if ( ! self::$location_type_registered ) {
			add_action( 'acf/init', array( $this, 'register_acf_location_type' ) );
			self::$location_type_registered = true;
		}

		$this->hooks_registered = true;
	}

	/**
	 * Register custom post type
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$supports = array( 'title' );

		if ( $this->config['revisions'] ) {
			$supports[] = 'revisions';
		}

		register_post_type(
			$this->config['post_type'],
			array(
				'labels'              => array(
					'name'          => $this->config['menu_label'],
					'singular_name' => $this->config['menu_label'],
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'menu_position'       => $this->config['menu_position'],
				'supports'            => $supports,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Maybe ensure pages exist
	 *
	 * @param \WP_Screen $screen Current screen.
	 * @return void
	 */
	public function maybe_ensure_pages_exist( \WP_Screen $screen ): void {
		// Only run on our post type screens.
		if ( $screen->post_type !== $this->config['post_type'] ) {
			return;
		}

		// Only run on list and edit screens.
		if ( ! in_array( $screen->base, array( 'edit', 'post' ), true ) ) {
			return;
		}

		$this->ensure_pages_exist();
	}

	/**
	 * Ensure all registered pages exist
	 *
	 * @return void
	 */
	private function ensure_pages_exist(): void {
		foreach ( $this->pages as $page ) {
			$this->create_page_if_not_exists( $page );
		}
	}

	/**
	 * Create a page post if it doesn't exist
	 *
	 * Uses cache to avoid repeated database queries for already-checked pages.
	 *
	 * @param Page $page Page object.
	 * @return void
	 */
	private function create_page_if_not_exists( Page $page ): void {
		$post_name = $this->get_post_name( $page->id );
		$post_type = $this->config['post_type'];

		// Check cache first - if we already processed this page, skip.
		if ( isset( $this->created_pages[ $page->id ] ) ) {
			return;
		}

		$existing = get_page_by_path( $post_name, OBJECT, $post_type );

		if ( $existing ) {
			// Update capability meta if changed.
			update_post_meta( $existing->ID, '_acf_options_capability', $page->capability );
			$this->created_pages[ $page->id ] = $existing->ID;
			return;
		}

		// Only check for conflicts if page doesn't exist yet.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conflict = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, post_type, post_title FROM {$wpdb->posts} WHERE post_name = %s AND post_type != %s LIMIT 1",
				$post_name,
				$post_type
			)
		);

		// If there's a slug conflict with another post type, log error and add admin notice.
		if ( $conflict ) {
			$error_message = sprintf(
				'ACF Options page "%s" could not be created because a %s with the slug "%s" already exists. Please use a different prefix in your Manager configuration.',
				$page->title,
				$conflict->post_type,
				$post_name
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'ACF Options: ' . $error_message );
			$this->creation_errors[]          = $error_message;
			$this->created_pages[ $page->id ] = false;
			return;
		}

		// Create the post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => $page->title,
				'post_name'   => $post_name,
				'post_type'   => $post_type,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'ACF Options: Failed to create page "%s" for instance "%s": %s',
					$page->id,
					$this->instance_key,
					$post_id->get_error_message()
				)
			);
			$this->created_pages[ $page->id ] = false;
			return;
		}

		update_post_meta( $post_id, '_acf_options_capability', $page->capability );

		if ( $page->description ) {
			update_post_meta( $post_id, '_acf_options_description', $page->description );
		}

		$this->created_pages[ $page->id ] = $post_id;
	}

	/**
	 * Show admin notice when ACF is missing
	 *
	 * @return void
	 */
	public function show_acf_missing_notice(): void {
		printf(
			'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
			esc_html__( 'ACF Options Manager', 'codesoup-acf-options' ),
			esc_html__( 'Advanced Custom Fields plugin is required. Please install and activate ACF.', 'codesoup-acf-options' )
		);
	}

	/**
	 * Show admin notices for page creation errors
	 *
	 * @return void
	 */
	public function show_creation_errors(): void {
		if ( empty( $this->creation_errors ) ) {
			return;
		}

		foreach ( $this->creation_errors as $error ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $error )
			);
		}
	}

	/**
	 * Remove date column from post list table
	 *
	 * @param array $columns The columns array.
	 * @return array
	 */
	public function remove_date_column( array $columns ): array {
		unset( $columns['date'] );
		return $columns;
	}

	/**
	 * Remove row actions from post list table
	 *
	 * @param array    $actions The actions array.
	 * @param \WP_Post $post The post object.
	 * @return array
	 */
	public function remove_row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type === $this->config['post_type'] ) {
			unset( $actions['inline hide-if-no-js'] );
			unset( $actions['trash'] );
		}
		return $actions;
	}

	/**
	 * Filter posts list to only show pages user has capability for
	 *
	 * Uses meta_query for efficient database-level filtering.
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function filter_posts_by_capability( \WP_Query $query ): void {
		// Only filter on admin list screen for our post type.
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== $this->config['post_type'] ) {
			return;
		}

		// Get all capabilities current user has.
		$user      = wp_get_current_user();
		$user_caps = array_keys( array_filter( $user->allcaps ) );

		if ( empty( $user_caps ) ) {
			// No capabilities - show nothing.
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		// Use meta_query to filter by capability at database level.
		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_acf_options_capability',
					'value'   => $user_caps,
					'compare' => 'IN',
				),
			)
		);
	}

	/**
	 * Check if current user can edit a page
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function can_edit_page( int $post_id ): bool {
		$capability = get_post_meta( $post_id, '_acf_options_capability', true );

		if ( empty( $capability ) ) {
			return false;
		}

		return current_user_can( $capability );
	}


	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		// Check if user can access at least one page.
		$has_access = false;
		foreach ( $this->pages as $page ) {
			if ( current_user_can( $page->capability ) ) {
				$has_access = true;
				break;
			}
		}

		// Don't show menu if user has no access to any page.
		if ( ! $has_access ) {
			return;
		}

		add_menu_page(
			$this->config['menu_label'],
			$this->config['menu_label'],
			'read',
			'edit.php?post_type=' . $this->config['post_type'],
			'',
			$this->config['menu_icon'],
			$this->config['menu_position']
		);
	}

	/**
	 * Save options to post_content
	 *
	 * Intentionally stores data in both ACF meta AND post_content.
	 * - ACF meta: Used by ACF for field rendering and validation
	 * - post_content: Used for fast retrieval via get_options()
	 *
	 * This double storage is by design to support both ACF's field system
	 * and efficient option retrieval without ACF overhead.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_options( $post_id ): void {
		// Only process our post type.
		if ( get_post_type( $post_id ) !== $this->config['post_type'] ) {
			return;
		}

		// Check user capability.
		if ( ! $this->can_edit_page( $post_id ) ) {
			return;
		}

		// Check if ACF is available.
		if ( ! function_exists( 'get_fields' ) ) {
			return;
		}

		// Get all ACF field values (already processed and saved by ACF).
		$fields = get_fields( $post_id );

		if ( empty( $fields ) ) {
			return;
		}

		// Serialize and save to post_content.
		$serialized = maybe_serialize( $fields );

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $serialized,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'ACF Options: Failed to save options for post %d: %s',
					$post_id,
					$result->get_error_message()
				)
			);
			return;
		}

		// Invalidate cache after successful save.
		$this->invalidate_cache( $post_id );
	}

	/**
	 * Register ACF location type
	 *
	 * @return void
	 */
	public function register_acf_location_type(): void {
		if ( ! function_exists( 'acf_register_location_type' ) ) {
			return;
		}

		acf_register_location_type( ACFOptionsLocation::class );
	}

	/**
	 * Get options by page ID (instance method)
	 *
	 * Retrieves options from post_content (serialized array).
	 * Uses object cache to avoid repeated database queries.
	 *
	 * @param string $page_id Page identifier.
	 * @return array Options array.
	 */
	public function get_options( $page_id ): array {
		$cache_key   = $this->get_cache_key( $page_id );
		$cache_group = 'acf_options';

		// Try to get from cache first.
		$cached = wp_cache_get( $cache_key, $cache_group );
		if ( $cached !== false ) {
			return $cached;
		}

		$post_name = $this->get_post_name( $page_id );
		$post_type = $this->config['post_type'];

		$post = get_page_by_path( $post_name, OBJECT, $post_type );

		if ( ! $post ) {
			// Cache empty result to avoid repeated lookups.
			wp_cache_set( $cache_key, array(), $cache_group );
			return array();
		}

		$content = $post->post_content;
		if ( empty( $content ) ) {
			// Cache empty result.
			wp_cache_set( $cache_key, array(), $cache_group );
			return array();
		}

		// Use maybe_unserialize for safety.
		$options = maybe_unserialize( $content );
		$options = is_array( $options ) ? $options : array();

		// Cache the result.
		wp_cache_set( $cache_key, $options, $cache_group );

		return $options;
	}

	/**
	 * Get single option by page ID and field name
	 *
	 * Retrieves single field value from postmeta using ACF's get_field().
	 *
	 * @param string $page_id Page identifier.
	 * @param string $field_name ACF field name.
	 * @param mixed  $default Default value if field not found.
	 * @return mixed Field value or default.
	 */
	public function get_option( string $page_id, string $field_name, $default = null ) {
		// Check if ACF is available.
		if ( ! function_exists( 'get_field' ) ) {
			return $default;
		}

		$post_name = $this->get_post_name( $page_id );
		$post_type = $this->config['post_type'];

		$post = get_page_by_path( $post_name, OBJECT, $post_type );

		if ( ! $post ) {
			return $default;
		}

		$value = get_field( $field_name, $post->ID );

		return $value !== false ? $value : $default;
	}

	/**
	 * Get post name from page ID
	 *
	 * @param string $page_id Page identifier.
	 * @return string
	 */
	private function get_post_name( string $page_id ): string {
		return $this->config['prefix'] . $page_id;
	}

	/**
	 * Get cache key for a page
	 *
	 * @param string $page_id Page identifier.
	 * @return string
	 */
	private function get_cache_key( string $page_id ): string {
		return 'options_' . $this->instance_key . '_' . $page_id;
	}

	/**
	 * Invalidate cache for a page
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function invalidate_cache( int $post_id ): void {
		$post      = get_post( $post_id );
		$post_name = $post->post_name;
		$prefix    = $this->config['prefix'];

		// Extract page_id from post_name.
		if ( strpos( $post_name, $prefix ) === 0 ) {
			$page_id   = substr( $post_name, strlen( $prefix ) );
			$cache_key = $this->get_cache_key( $page_id );
			wp_cache_delete( $cache_key, 'acf_options' );
		}
	}

	/**
	 * Get instance key
	 *
	 * @return string
	 */
	public function get_instance_key(): string {
		return $this->instance_key;
	}

	/**
	 * Get config
	 *
	 * @return array
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Get pages
	 *
	 * @return array<Page>
	 */
	public function get_pages(): array {
		return $this->pages;
	}
}
