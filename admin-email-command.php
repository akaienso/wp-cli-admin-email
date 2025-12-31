<?php
/**
 * WP-CLI command: wp admin-email
 *
 * Usage:
 *   wp admin-email
 *   wp admin-email --dry-run
 *   wp admin-email set <email> [--url=<siteurl>] [--network] [--dry-run]
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Admin_Email_Command {

	/**
	 * Interactive: detect single vs multisite, display current admin email(s),
	 * and optionally update.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would change, but do not write changes.
	 *
	 * ## EXAMPLES
	 *
	 *   wp admin-email
	 *   wp admin-email --dry-run
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( is_multisite() ) {
			WP_CLI::log( "Detected: multisite ✅" );
			$this->render_network_table();

			$choice = $this->prompt_key( "Options: [S]et email, [R]efresh list, [Q]uit: ", [ 's', 'r', 'q' ] );
			while ( $choice !== 'q' ) {
				if ( $choice === 'r' ) {
					$this->render_network_table();
				}
				if ( $choice === 's' ) {
					$new_email = $this->prompt_line( "New admin email: " );
					if ( empty( $new_email ) ) {
						WP_CLI::warning( "Email cannot be blank." );
					} else {
						$site_url = $this->prompt_line( "Optional: site URL (blank = ALL sites): " );
						$this->confirm_or_abort( $new_email, $site_url, true, $dry_run );

						if ( ! empty( $site_url ) ) {
							$this->update_one_site( $site_url, $new_email, $dry_run );
						} else {
							$this->update_all_sites( $new_email, $dry_run );
						}
					}
				}
				$choice = $this->prompt_key( "Options: [S]et email, [R]efresh list, [Q]uit: ", [ 's', 'r', 'q' ] );
			}

			WP_CLI::log( "Bye. 👋" );
			return;
		}

		// Single site.
		WP_CLI::log( "Detected: single site ✅" );
		$current = get_option( 'admin_email' );
		\WP_CLI\Utils\format_items( 'table', [ [ 'admin_email' => $current ] ], [ 'admin_email' ] );

		$choice = $this->prompt_key( "Options: [S]et email, [Q]uit: ", [ 's', 'q' ] );
		if ( $choice === 'q' ) {
			WP_CLI::log( "Bye. 👋" );
			return;
		}

		$new_email = $this->prompt_line( "New admin email: " );
		if ( empty( $new_email ) ) {
			WP_CLI::warning( "Email cannot be blank." );
			return;
		}

		$this->confirm_or_abort( $new_email, '', false, $dry_run );

		if ( $dry_run ) {
			WP_CLI::log( "[DRY RUN] Would set admin_email from '{$current}' to '{$new_email}'" );
		} else {
			update_option( 'admin_email', $new_email );
			WP_CLI::success( "Updated admin_email to {$new_email}" );
		}
	}

	/**
	 * Non-interactive setter.
	 *
	 * ## OPTIONS
	 * <email>
	 * : Email to set.
	 *
	 * [--url=<siteurl>]
	 * : For multisite, update only this site URL.
	 *
	 * [--network]
	 * : For multisite, update ALL sites (ignores --url).
	 *
	 * [--dry-run]
	 * : Show what would change, but do not write changes.
	 *
	 * ## EXAMPLES
	 *   wp admin-email set admin@example.com
	 *   wp admin-email set admin@example.com --network
	 *   wp admin-email set admin@example.com --url=https://example.com/subsite/
	 */
	public function set( $args, $assoc_args ) {
		$new_email = $args[0] ?? '';
		if ( empty( $new_email ) ) {
			WP_CLI::error( "Email is required." );
		}

		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! is_multisite() ) {
			$current = get_option( 'admin_email' );
			if ( $dry_run ) {
				WP_CLI::log( "[DRY RUN] Would set admin_email from '{$current}' to '{$new_email}'" );
				return;
			}
			update_option( 'admin_email', $new_email );
			WP_CLI::success( "Updated admin_email to {$new_email}" );
			return;
		}

		$network = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false );
		$url     = $assoc_args['url'] ?? '';

		if ( $network ) {
			$this->update_all_sites( $new_email, $dry_run );
			return;
		}

		if ( ! empty( $url ) ) {
			$this->update_one_site( $url, $new_email, $dry_run );
			return;
		}

		WP_CLI::error( "On multisite, provide either --network or --url=<siteurl>, or run `wp admin-email` for interactive mode." );
	}

	private function render_network_table() {
		$rows = [];
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

	private function update_one_site( string $site_url, string $new_email, bool $dry_run ) {
		$site_id = $this->site_url_to_blog_id( $site_url );
		if ( ! $site_id ) {
			WP_CLI::error( "Could not find site for URL: {$site_url}" );
		}

		switch_to_blog( $site_id );
		$current = (string) get_option( 'admin_email' );
		restore_current_blog();

		if ( $dry_run ) {
			WP_CLI::log( "[DRY RUN] {$site_url}: '{$current}' -> '{$new_email}'" );
			return;
		}

		switch_to_blog( $site_id );
		update_option( 'admin_email', $new_email );
		restore_current_blog();

		WP_CLI::success( "Updated {$site_url} admin_email to {$new_email}" );
	}

	private function update_all_sites( string $new_email, bool $dry_run ) {
		$sites = get_sites( [ 'number' => 0 ] );
		foreach ( $sites as $site ) {
			switch_to_blog( (int) $site->blog_id );
			$url     = get_site_url();
			$current = (string) get_option( 'admin_email' );

			if ( $dry_run ) {
				WP_CLI::log( "[DRY RUN] {$url}: '{$current}' -> '{$new_email}'" );
			} else {
				update_option( 'admin_email', $new_email );
				WP_CLI::log( "Updated {$url} -> {$new_email}" );
			}

			restore_current_blog();
		}
		if ( ! $dry_run ) {
			WP_CLI::success( "Network update complete." );
		}
	}

	private function site_url_to_blog_id( string $url ) : int {
		$url = trim( $url );

		// Try core helper first (works for many cases).
		if ( function_exists( 'url_to_blogid' ) ) {
			$id = (int) url_to_blogid( $url );
			if ( $id > 0 ) {
				return $id;
			}
		}

		// Fallback: parse and match domain/path.
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return 0;
		}
		$domain = strtolower( $parts['host'] );
		$path   = isset( $parts['path'] ) ? trailingslashit( $parts['path'] ) : '/';

		$site = get_sites( [
			'number' => 1,
			'domain' => $domain,
			'path'   => $path,
		] );

		return empty( $site ) ? 0 : (int) $site[0]->blog_id;
	}

	private function prompt_line( string $prompt ) : string {
		$val = \cli\prompt( $prompt, null, '' );
		return is_string( $val ) ? trim( $val ) : '';
	}

	private function prompt_key( string $prompt, array $allowed_lowercase ) : string {
		while ( true ) {
			$char = \cli\prompt( $prompt, null, '' );
			$char = strtolower( trim( (string) $char ) );

			// Allow Enter to count as '' only if explicitly allowed.
			if ( $char === '' && in_array( '', $allowed_lowercase, true ) ) {
				return '';
			}

			if ( in_array( $char, $allowed_lowercase, true ) ) {
				return $char;
			}
			WP_CLI::warning( "Invalid option." );
		}
	}

	private function confirm_or_abort( string $new_email, string $site_url, bool $is_multisite, bool $dry_run ) {
		WP_CLI::log( "" );
		if ( $is_multisite ) {
			if ( $site_url ) {
				WP_CLI::log( "Plan: set admin_email='{$new_email}' for ONE site: {$site_url}" );
			} else {
				WP_CLI::log( "Plan: set admin_email='{$new_email}' for ALL sites in the network." );
			}
		} else {
			WP_CLI::log( "Plan: set admin_email='{$new_email}' for this site." );
		}
		WP_CLI::log( $dry_run ? "Mode: DRY RUN (no writes)" : "Mode: LIVE (will write changes)" );

		$ok = \cli\confirm( "Continue?", true );
		if ( ! $ok ) {
			WP_CLI::error( "Aborted." );
		}
	}
}

WP_CLI::add_command( 'admin-email', 'Admin_Email_Command' );
