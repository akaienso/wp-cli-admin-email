<?php
declare(strict_types=1);

namespace Akaienso\WP_CLI;

/**
 * WP-CLI command: wp admin-email
 *
 * Interactive and non-interactive tooling to view/update WordPress `admin_email`
 * for single-site and multisite installs.
 *
 * Includes pagination for large multisite networks and an interactive Help pager
 * that displays README.md with a generated table of contents.
 *
 * v1.1.2
 */

if ( ! defined( 'WP_CLI' ) || ! \WP_CLI ) {
	return;
}

class Admin_Email_Command {

	private const DEFAULT_TERMINAL_HEIGHT = 24;
	private const RESERVED_LINES = 8;
	private const MIN_PAGE_SIZE = 10;
	private const MAX_PAGE_SIZE = 50;
	private const UPDATE_BATCH_SIZE = 100;
	private const HELP_PAGER_RESERVED_LINES = 2;
	private const HELP_PAGER_MIN_SIZE = 8;

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

		// Multisite flow
		if ( is_multisite() ) {
			\WP_CLI::log( 'Detected multisite network.' );
			$this->render_network_table();

			while ( true ) {
				$choice = \cli\prompt( 'Options: [L]ist sites, [S]et email, [H]elp, [Q]uit', null, '' );
				$choice = strtolower( trim( (string) $choice ) );

				if ( $choice === 'q' ) {
					return;
				}

				if ( $choice === 'l' ) {
					$this->render_network_table();
					continue;
				}

				if ( $choice === 'h' ) {
					$this->show_help();
					// Restore context after help.
					$this->render_network_table();
					continue;
				}

				if ( $choice !== 's' ) {
					continue;
				}

			$new_email = $this->prompt_and_validate_email();
			if ( null === $new_email ) {
				continue;
			}

			$site_url = trim( (string) \cli\prompt( 'Optional site URL (blank = ALL sites):' ) );
				$this->confirm_or_abort( $new_email, $site_url ?: null, $dry_run );

				if ( $site_url ) {
					$this->update_one_site( $site_url, $new_email, $dry_run );
				} else {
					$this->update_all_sites( $new_email, $dry_run );
				}

				// After an approved and executed change (or dry-run), show current status (paged).
				$this->render_network_table();
			}
		}

		// Single-site flow
		$current = (string) get_option( 'admin_email' );
		$this->render_single_site_table( $current );

		while ( true ) {
			$choice = \cli\prompt( 'Options: [L]ist, [S]et email, [H]elp, [Q]uit', null, '' );
			$choice = strtolower( trim( (string) $choice ) );

			if ( $choice === 'q' ) {
				return;
			}

			if ( $choice === 'l' ) {
				$current = (string) get_option( 'admin_email' );
				$this->render_single_site_table( $current );
				continue;
			}

			if ( $choice === 'h' ) {
				$this->show_help();
				// Restore context after help.
				$current = (string) get_option( 'admin_email' );
				$this->render_single_site_table( $current );
				continue;
			}

			if ( $choice !== 's' ) {
				continue;
			}

			$new_email = $this->prompt_and_validate_email();
			if ( null === $new_email ) {
				continue;
			}

			$this->confirm_or_abort( $new_email, null, $dry_run );

			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] Would update admin_email from '{$current}' to '{$new_email}'." );
			} else {
				$result = update_option( 'admin_email', $new_email );
				if ( false === $result ) {
					\WP_CLI::error( 'Failed to update admin_email. Check database permissions.' );
				}
				\WP_CLI::success( "Updated admin_email to {$new_email}." );
			}

			// After an approved and executed change (or dry-run), show current status.
			$current = (string) get_option( 'admin_email' );
			$this->render_single_site_table( $current );
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
		$new_email = sanitize_email( trim( (string) ( $args[0] ?? '' ) ) );
		if ( empty( $new_email ) || ! is_email( $new_email ) ) {
			\WP_CLI::error( 'Invalid email address.' );
		}

		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! is_multisite() ) {
			$current = (string) get_option( 'admin_email' );

			if ( $dry_run ) {
				\WP_CLI::log( "[DRY RUN] Would update admin_email from '{$current}' to '{$new_email}'." );
				$this->render_single_site_table( $current );
				return;
			}

			$result = update_option( 'admin_email', $new_email );
			if ( false === $result ) {
				\WP_CLI::error( 'Failed to update admin_email. Check database permissions.' );
			}
			\WP_CLI::success( "Updated admin_email to {$new_email}." );

			$current = (string) get_option( 'admin_email' );
			$this->render_single_site_table( $current );
			return;
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false ) ) {
			$this->update_all_sites( $new_email, $dry_run );

			// If live, show current status (paged).
			if ( ! $dry_run ) {
				$this->render_network_table();
			}
			return;
		}

		if ( ! empty( $assoc_args['url'] ) ) {
			$this->update_one_site( (string) $assoc_args['url'], $new_email, $dry_run );

			// If live, show current status (paged).
			if ( ! $dry_run ) {
				$this->render_network_table();
			}
			return;
		}

		\WP_CLI::error( 'On multisite, specify --network or --url=<siteurl>.' );
	}

	/**
	 * Get current admin email.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<siteurl>]
	 * : Get admin email for a specific site (multisite).
	 *
	 * [--network]
	 * : Get admin emails for all sites in a multisite network.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 * wp admin-email get
	 * wp admin-email get --url=https://example.com/subsite/
	 * wp admin-email get --network
	 * wp admin-email get --format=json
	 */
	public function get( $args, $assoc_args ) {
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( ! is_multisite() ) {
			$admin_email = (string) get_option( 'admin_email' );
			$data = [ [ 'admin_email' => $admin_email ] ];
			\WP_CLI\Utils\format_items( $format, $data, [ 'admin_email' ] );
			return;
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'network', false ) ) {
			$rows = [];
			foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
				switch_to_blog( (int) $site->blog_id );
				$rows[] = [
					'url'         => get_site_url(),
					'admin_email' => (string) get_option( 'admin_email' ),
				];
				restore_current_blog();
			}
			\WP_CLI\Utils\format_items( $format, $rows, [ 'url', 'admin_email' ] );
			return;
		}

		if ( ! empty( $assoc_args['url'] ) ) {
			$blog_id = (int) \url_to_blogid( (string) $assoc_args['url'] );
			if ( ! $blog_id ) {
				\WP_CLI::error( "Could not find site for URL: {$assoc_args['url']}" );
			}

			switch_to_blog( $blog_id );
			$admin_email = (string) get_option( 'admin_email' );
			$url = get_site_url();
			restore_current_blog();

			$data = [ [ 'url' => $url, 'admin_email' => $admin_email ] ];
			\WP_CLI\Utils\format_items( $format, $data, [ 'url', 'admin_email' ] );
			return;
		}

		// Default: get current site's admin email
		$admin_email = (string) get_option( 'admin_email' );
		$data = [ [ 'admin_email' => $admin_email ] ];
		\WP_CLI\Utils\format_items( $format, $data, [ 'admin_email' ] );
	}

	/**
	 * Render single-site status table.
	 */
	private function render_single_site_table( string $admin_email ) : void {
		\WP_CLI\Utils\format_items(
			'table',
			[ [ 'admin_email' => $admin_email ] ],
			[ 'admin_email' ]
		);
	}

	/**
	 * Render multisite admin_email status with pagination for large networks.
	 */
	private function render_network_table( int $page_size = 0 ) : void {
		$page_size = $page_size > 0 ? $page_size : $this->get_page_size();

		$total = $this->get_total_sites();
		if ( $total <= 0 ) {
			\WP_CLI::log( 'No sites found.' );
			return;
		}

		$offset = 0;

		while ( $offset < $total ) {
			$sites = get_sites(
				[
					'number' => $page_size,
					'offset' => $offset,
				]
			);

			$rows = [];
			foreach ( $sites as $site ) {
				switch_to_blog( (int) $site->blog_id );
				$rows[] = [
					'url'         => get_site_url(),
					'admin_email' => (string) get_option( 'admin_email' ),
				];
				restore_current_blog();
			}

			\WP_CLI\Utils\format_items( 'table', $rows, [ 'url', 'admin_email' ] );

			$offset += count( $sites );
			if ( $offset >= $total ) {
				return;
			}

			$start = max( 1, $offset - count( $sites ) + 1 );
			$end   = $offset;

			\WP_CLI::log( sprintf( 'Showing %d–%d of %d', $start, $end, $total ) );

			$action = strtolower( trim( (string) \cli\prompt( '[Enter] next page, [Q] quit', null, '' ) ) );
			if ( $action === 'q' ) {
				return;
			}
		}
	}

	/**
	 * Interactive Help pager: loads README.md and displays it page-by-page.
	 * Includes a generated table of contents from Markdown headings.
	 */
	private function show_help() : void {
		$readme_path = $this->locate_readme();
		if ( ! $readme_path || ! is_readable( $readme_path ) ) {
			\WP_CLI::warning( 'README.md not found or not readable.' );
			return;
		}

		$raw  = (string) file_get_contents( $readme_path );
		$raw  = str_replace( [ "\r\n", "\r" ], "\n", $raw );
		$lines = explode( "\n", $raw );

		$toc_lines = $this->build_toc_lines( $lines );

		$help_lines   = [];
		$help_lines[] = 'HELP: wp-cli-admin-email (README.md)';
		$help_lines[] = 'File: ' . $readme_path;
		$help_lines[] = str_repeat( '-', 60 );
		$help_lines[] = 'TABLE OF CONTENTS';
		$help_lines[] = str_repeat( '-', 60 );
		$help_lines   = array_merge( $help_lines, $toc_lines ?: [ '(No headings found.)' ] );
		$help_lines[] = '';
		$help_lines[] = str_repeat( '-', 60 );
		$help_lines[] = 'README';
		$help_lines[] = str_repeat( '-', 60 );
		$help_lines   = array_merge( $help_lines, $lines );

		$this->page_lines( $help_lines );
	}

	/**
	 * Flat repo: README.md lives next to this file.
	 * Also allow readme.md for convenience on case-sensitive FS.
	 */
	private function locate_readme() : ?string {
		foreach ( [ 'README.md', 'readme.md' ] as $file ) {
			$path = __DIR__ . '/' . $file;
			if ( is_file( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	/**
	 * Build a simple TOC from Markdown headings.
	 * Supports #..######. Indents based on level.
	 */
	private function build_toc_lines( array $lines ) : array {
		$toc = [];

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(#{1,6})\s+(.+?)\s*$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				$title = trim( $m[2] );

				$indent = str_repeat( '  ', max( 0, $level - 1 ) );
				$toc[]  = $indent . '- ' . $title;
			}
		}

		return $toc;
	}

	/**
	 * Page an array of lines using terminal height.
	 * Controls:
	 *  - Enter: next page
	 *  - B: back one page
	 *  - Q: quit
	 */
	private function page_lines( array $lines ) : void {
		$page_size = $this->get_page_size();

		// Reserve a couple lines for the prompt/footer.
		$page_size = max( self::HELP_PAGER_MIN_SIZE, $page_size - self::HELP_PAGER_RESERVED_LINES );

		$total_lines = count( $lines );
		$offset      = 0;

		while ( true ) {
			\WP_CLI::log( '' );

			$chunk = array_slice( $lines, $offset, $page_size );
			foreach ( $chunk as $l ) {
				\WP_CLI::log( $l );
			}

			$end = min( $offset + $page_size, $total_lines );
			\WP_CLI::log( sprintf( 'Lines %d–%d of %d', $offset + 1, $end, $total_lines ) );

			if ( $end >= $total_lines ) {
				$action = strtolower( trim( (string) \cli\prompt( '[Q] quit, [B] back', null, '' ) ) );
				if ( $action === 'b' ) {
					$offset = max( 0, $offset - $page_size );
					continue;
				}
				return;
			}

			$action = strtolower( trim( (string) \cli\prompt( '[Enter] next, [B] back, [Q] quit', null, '' ) ) );
			if ( $action === 'q' ) {
				return;
			}
			if ( $action === 'b' ) {
				$offset = max( 0, $offset - $page_size );
				continue;
			}

			$offset = min( $total_lines, $offset + $page_size );
		}
	}

	private function get_total_sites() : int {
		$count = get_sites( [ 'count' => true ] );
		return (int) $count;
	}

	private function get_page_size() : int {
		$lines = (int) getenv( 'LINES' );
		if ( $lines <= 0 ) {
			$lines = self::DEFAULT_TERMINAL_HEIGHT;
		}

		$page = $lines - self::RESERVED_LINES;

		if ( $page < self::MIN_PAGE_SIZE ) {
			$page = self::MIN_PAGE_SIZE;
		}
		if ( $page > self::MAX_PAGE_SIZE ) {
			$page = self::MAX_PAGE_SIZE;
		}

		return $page;
	}

	private function update_one_site( string $site_url, string $email, bool $dry_run ) : void {
		$blog_id = (int) \url_to_blogid( $site_url );
		if ( ! $blog_id ) {
			\WP_CLI::error( "Could not find site for URL: {$site_url}" );
		}

		switch_to_blog( $blog_id );

		$current = (string) get_option( 'admin_email' );
		$url     = get_site_url();

		if ( $dry_run ) {
			\WP_CLI::log( "[DRY RUN] {$url}: '{$current}' → '{$email}'" );
		} else {
			$result = update_option( 'admin_email', $email );
			if ( false === $result ) {
				restore_current_blog();
				\WP_CLI::error( "Failed to update admin_email for {$url}. Check database permissions." );
			}
			\WP_CLI::success( "Updated {$url} to {$email}" );
		}

		restore_current_blog();
	}

	private function update_all_sites( string $email, bool $dry_run ) : void {
		$failures = [];
		$batch_size = self::UPDATE_BATCH_SIZE;
		$total = $this->get_total_sites();
		$offset = 0;

		if ( ! $dry_run && $total > 1 ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Updating sites', $total );
		}

		while ( $offset < $total ) {
			$sites = get_sites(
				[
					'number' => $batch_size,
					'offset' => $offset,
				]
			);

			foreach ( $sites as $site ) {
				switch_to_blog( (int) $site->blog_id );
				$url     = get_site_url();
				$current = (string) get_option( 'admin_email' );

				if ( $dry_run ) {
					\WP_CLI::log( "[DRY RUN] {$url}: '{$current}' → '{$email}'" );
				} else {
					$result = update_option( 'admin_email', $email );
					if ( false === $result ) {
						$failures[] = $url;
						\WP_CLI::warning( "Failed to update {$url}. Check database permissions." );
					}
				}

				if ( ! $dry_run && isset( $progress ) ) {
					$progress->tick();
				}

				restore_current_blog();
			}

			$offset += count( $sites );
		}

		if ( isset( $progress ) ) {
			$progress->finish();
		}

		if ( ! $dry_run ) {
			if ( empty( $failures ) ) {
				\WP_CLI::success( 'Network update complete.' );
			} else {
				\WP_CLI::warning( sprintf( 'Network update complete with %d failure(s).', count( $failures ) ) );
			}
		}
	}

	/**
	 * Prompt for email address and validate it.
	 *
	 * @return string|null Valid email address, or null if validation failed.
	 */
	private function prompt_and_validate_email() : ?string {
		$email = sanitize_email( trim( (string) \cli\prompt( 'New admin email:' ) ) );
		if ( empty( $email ) || ! is_email( $email ) ) {
			\WP_CLI::warning( 'Invalid email address.' );
			return null;
		}
		return $email;
	}

	private function confirm_or_abort( string $email, ?string $site_url, bool $dry_run ) : void {
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

\WP_CLI::add_command( 'admin-email', 'Akaienso\WP_CLI\Admin_Email_Command' );
