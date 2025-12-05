<?php
/**
 * Page Value Object
 *
 * @package CodeSoup\ACFOptions
 */

namespace CodeSoup\ACFOptions;

// Don't allow direct access to file.
defined( 'ABSPATH' ) || die;

/**
 * Page class
 *
 * Represents an ACF options page configuration.
 *
 * @since 1.0.0
 */
class Page {

	/**
	 * Page ID
	 *
	 * @var string
	 */
	public readonly string $id;

	/**
	 * Page title
	 *
	 * @var string
	 */
	public readonly string $title;

	/**
	 * Required capability
	 *
	 * @var string
	 */
	public readonly string $capability;

	/**
	 * Page description
	 *
	 * @var string|null
	 */
	public readonly ?string $description;

	/**
	 * Constructor
	 *
	 * @param array $args Page arguments.
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	public function __construct( array $args ) {
		if ( empty( $args['id'] ) ) {
			throw new \InvalidArgumentException( 'Page ID is required' );
		}

		if ( empty( $args['title'] ) ) {
			throw new \InvalidArgumentException( 'Page title is required' );
		}

		if ( empty( $args['capability'] ) ) {
			throw new \InvalidArgumentException( 'Page capability is required' );
		}

		$this->id          = $args['id'];
		$this->title       = $args['title'];
		$this->capability  = $args['capability'];
		$this->description = $args['description'] ?? null;
	}
}
