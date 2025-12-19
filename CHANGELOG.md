# Changelog

All notable changes to this project will be documented in this file.

## [1.0.2] - 2024-12-19

### Added

- `register_pages()` method to register multiple options pages at once
- `get_option()` method to retrieve single field value from postmeta
- `debug()` static method to get complete instance state (config, pages, and values)
- Object caching for `get_options()` to prevent duplicate database queries
- Cache invalidation on save to ensure fresh data after updates
- ACF dependency checking - plugin now gracefully handles missing ACF
- Admin notice when ACF is not available
- `migrate()` static method for handling configuration changes and capability syncing
- Support for disabling revisions via `revisions` config option (defaults to false)
- Documentation for post locking and optional revision history
- Installation instructions for WordPress plugin installation from GitHub

### Changed

- Revisions are now disabled by default (can be enabled with `'revisions' => true`)
- Post type registration now conditionally adds revision support based on config
- Improved error handling in `get_options()` and `get_option()` methods
- Updated README with comprehensive examples and API documentation
- Enhanced migration documentation with examples

### Fixed

- Prevented fatal errors when ACF is not installed or activated
- Post type and admin menu no longer registered when ACF is missing
- Cache consistency issues after saving options

## [1.0.1] - 2024-12-13

### Fixed

- Bug fixes

### Changed

- README update

## [1.0.0] - 2024-12-05

### Added

- Initial release as Composer package
- Manager class with factory pattern for creating instances
- Page value object for defining options pages
- ACF location rule integration for field group assignment
- Support for multiple Manager instances with different instance keys
- Capability-based access control for options pages
- Automatic page creation and management
- Methods for retrieving, exporting, and cleaning up options data
- Readonly Page properties for immutability after validation
- Error logging and proper error handling throughout
- Type hints and strict typing (PHP 8.1+)
