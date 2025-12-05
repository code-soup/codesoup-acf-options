<?php
/**
 * ACF Location Rule for ACF Options
 *
 * @package CodeSoup\ACFOptions
 */

namespace CodeSoup\ACFOptions;

// Don't allow direct access to file.
defined( 'ABSPATH' ) || die;

/**
 * ACF Options Location class
 *
 * Provides ACF location rule for assigning field groups to options pages.
 *
 * @since 1.0.0
 */
class ACFOptionsLocation extends \ACF_Location {

	/**
	 * Initialize the location type
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->name        = 'codesoup_acf_options';
		$this->label       = __( 'CodeSoup ACF Options', 'codesoup-acf-options' );
		$this->category    = 'forms';
		$this->object_type = 'post';
	}

	/**
	 * Get available values for this location
	 *
	 * @param array $rule The location rule.
	 * @return array
	 */
	public function get_values( $rule ): array {
		$choices = array();

		// Get all Manager instances.
		$managers = Manager::get_all();

		foreach ( $managers as $manager ) {
			$instance_key = $manager->get_instance_key();
			$config       = $manager->get_config();
			$pages        = $manager->get_pages();

			foreach ( $pages as $page ) {
				$key             = $instance_key . ':' . $page->id;
				$choices[ $key ] = sprintf(
					'%s - %s',
					$config['menu_label'],
					$page->title
				);
			}
		}

		return $choices;
	}

	/**
	 * Match the location rule
	 *
	 * @param array $rule The location rule.
	 * @param array $screen The screen data.
	 * @param array $field_group The field group data.
	 * @return bool
	 */
	public function match( $rule, $screen, $field_group ): bool {
		// Parse the rule value (format: "instance_key:page_id").
		$parts = explode( ':', $rule['value'] );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $instance_key, $page_id ) = $parts;

		// Get the Manager instance.
		$manager = Manager::get( $instance_key );
		if ( ! $manager ) {
			return false;
		}

		// Get current post type and post name.
		$post_type = $screen['post_type'] ?? '';
		$post_id   = $screen['post_id'] ?? 0;

		// Check if we're on the correct post type.
		$config = $manager->get_config();
		if ( $post_type !== $config['post_type'] ) {
			return false;
		}

		// If editing an existing post, check the post name.
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return false;
			}

			$expected_post_name = $config['prefix'] . $page_id;
			$matches            = ( $post->post_name === $expected_post_name );

			return $this->compare( $matches, $rule );
		}

		return false;
	}

	/**
	 * Compare the match result with the rule operator
	 *
	 * @param bool  $result The match result.
	 * @param array $rule The location rule.
	 * @return bool
	 */
	private function compare( bool $result, array $rule ): bool {
		$operator = $rule['operator'] ?? '==';

		if ( '!=' === $operator ) {
			return ! $result;
		}

		return $result;
	}
}
