<?php
/**
 * WP-CLI command: wp admin-email
 *
 * Provides an interactive and non-interactive way to view and update
 * the WordPress admin_email option for single-site and multisite installs.
 *
 * v1.0.0
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Admin_Email_Command {

	/**
	 * Interactive command.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would change, but do not write changes.
	 *
	 * ## EXAMPLES
	 *
	 * wp admin-email
	 * wp admin-email --dry-run
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( is_multisite() ) {
			\WP_CLI::log( 'Detected multisite network.' );
			$this->render_network_table();

			while ( true ) {
				$choice = \cli\prompt( 'Options: [S]et email, [R]efresh, [Q]uit', null, '' );
				$choice = strtolower( trim( (string) $choice ) );

				if ( $choice === 'q' ) {
					return;
				}

				if ( $choice === 'r' ) {
					$this->render_network_table();
					continue;
				}

				if ( $choice === 's' ) {
					$new_email = trim( (string) \cli\prompt( 'New admin email:' ) );
					if ( empty( $new_email ) ) {
						\WP_CLI::warning( 'Email cannot be blank.' );
						continue;
					}

					$site_url = trim( (string) \cli\prompt( 'Optional site URL (blank = ALL sites):' ) );
					$this->confirm_or_abort( $new_email, $site_url ?: null, $dry_run );

					if ( $site_url ) {
						$this->update_one_site( $site_url, $new_email, $dry_run );
					} else {
						$this->update_all_sites( $new_email, $dry_run );
					}

					// After an approved and executed change (or dry-run), show current status.
					$this->render_network_table();
				}
			}
		}

		// Single-site flow
		$current = get_option( 'admin_email' );
		\WP_CLI\Utils\format_items(
			'table',
			[ [ 'admin_email' => $current ] ],
			[ 'admin_email' ]
		);

		$choice = strtolower( trim( (string) \cli\prompt( 'Options: [S]et email, [Q]uit', null, '' ) ) );
		if ( $choice !== 's' ) {
			return;
		}

		$new_email = trim( (string) \cli\prompt( 'New admin email:' ) );
		if ( empty( $new_email ) ) {
			\WP_CLI::warning( 'Email cannot be blank.' );
			return;
		}

		$this->confirm_or_abort( $new_email, null, $dry_run );

		if ( $dry_run ) {
			\WP_CLI::log( "[DRY RUN] Would update admin_email from '{$current}' to '{$new_email}'." );
		} else {
			update_option( 'admin_email', $new_email );
			\WP_CLI::success( "Updated admin_email to {$new_email}." );

			// After an approved and executed change, show current status.
			$current = get_option( 'admin_email' );
			\WP_CLI\Utils\format_items(
				'table',
				[ [ 'admin_email' => $current ] ],
				[ 'admin_email' ]
			);
		}
	}

	/**
	 * Non-interactive setter.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : New admin email address.
	 *
	 * [--network]
	 * : Apply to all sites in a multisite network.
	 *
	 * [--url=<siteurl>]
	 * : Apply only to a specific site URL (multisite).
	 *
	 * [--dry-run]
	 * : Show what would change without writing.
	 *
	 * ## EXAMPLES
	 *
	 * wp admin-email set user@example.com
	 * wp admin-email set user@example.com --network
	 * wp admin-email set user@example.com --url=https://example.com/subsite/
	 * wp admin-email set user@example.com --dry-run
	 */
	public function set( $args, $assoc_args ) {
		$new_email = trim( (string) ( $args[0] ?? '' ) );
		if ( empty( $new_email ) ) {
			\WP_CLI::error( 'Email is required.' );
		}

		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! is_multisite() ) {
			$current = get_option( 'admin_email' );
			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] Would update admin_email from '{$current}' to '{$new_email}'." );
				return;
			}

			update_option( 'admin_email', $new_email );
			\WP_CLI::success( "Updated admin_email to {$new_email}." );

			// After an executed change, show current status.
			$current = get_option( 'admin_email' );
			\WP_CLI\Utils\format_items(
				'table',
				[ [ 'admin_email' => $current ] ],
				[ 'admin_email' ]
			);

			return;
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false ) ) {
			$this->update_all_sites( $new_email, $dry_run );
			return;
		}

		if ( ! empty( $assoc_args['url'] ) ) {
			$this->update_one_site( $assoc_args['url'], $new_email, $dry_run );

			// If this was a live update, show current status.
			if ( ! $dry_run ) {
				$this->render_network_table();
			}

			return;
		}

		\WP_CLI::error( 'On multisite, specify --network or --url=<siteurl>.' );
	}

	private function render_network_table() {
		$rows  = [];
		$sites = get_sites( [ 'number' => 0 ] );

		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			$rows[] = [
				'url'         => get_site_url(),
				'admin_email' => (string) get_option( 'admin_email' ),
			];
			restore_current_blog();
		}

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'url', 'admin_email' ] );
	}

	private function update_one_site( string $site_url, string $email, bool $dry_run ) {
		$blog_id = (int) url_to_blogid( $site_url );
		if ( ! $blog_id ) {
			\WP_CLI::error( "Could not find site for URL: {$site_url}" );
		}

		switch_to_blog( $blog_id );
		$current = (string) get_option( 'admin_email' );

		if ( $dry_run ) {
			\WP_CLI::log( "[DRY RUN] {$site_url}: '{$current}' → '{$email}'" );
		} else {
			update_option( 'admin_email', $email );
			\WP_CLI::success( "Updated {$site_url} to {$email}" );
		}

		restore_current_blog();
	}

	private function update_all_sites( string $email, bool $dry_run ) {
		foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
			switch_to_blog( (int) $site->blog_id );
			$url     = get_site_url();
			$current = (string) get_option( 'admin_email' );

			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] {$url}: '{$current}' → '{$email}'" );
			} else {
				update_option( 'admin_email', $email );
				\WP_CLI::log( "Updated {$url}" );
			}

			restore_current_blog();
		}

		if ( ! $dry_run ) {
			\WP_CLI::success( 'Network update complete.' );

			// After an executed network-wide change, show current status.
			$this->render_network_table();
		}
	}

	private function confirm_or_abort( string $email, ?string $site_url, bool $dry_run ) {
		\WP_CLI::log( '' );

		if ( is_multisite() ) {
			if ( $site_url ) {
				\WP_CLI::log( "Target: {$site_url}" );
			} else {
				\WP_CLI::log( 'Target: all sites' );
			}
		} else {
			\WP_CLI::log( 'Target: this site' );
		}

		\WP_CLI::log( "Email: {$email}" );
		\WP_CLI::log( $dry_run ? 'Mode: DRY RUN' : 'Mode: LIVE' );

		if ( ! \cli\confirm( 'Continue?' ) ) {
			\WP_CLI::error( 'Aborted.' );
		}
	}
}

\WP_CLI::add_command( 'admin-email', 'Admin_Email_Command' );
