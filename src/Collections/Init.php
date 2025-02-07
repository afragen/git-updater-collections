<?php
/**
 * Git Updater Collections
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater-collections
 * @package   git-updater-collections
 */

namespace Fragen\Git_Updater\Collections;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Init
 */
class Init {

	/**
	 * Initialize, load filters, and get started.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'install_plugins_tabs', [ $this, 'add_install_tab' ], 10, 1 );
		add_filter( 'install_plugins_table_api_args_third-party', [ $this, 'add_install_third_party_args' ], 10, 1 );
		add_filter( 'plugins_api_result', [ $this, 'plugins_api_result' ], 10, 3 );

		add_action( 'install_plugins_third-party', 'display_plugins_table' );
		add_action(
			'install_plugins_table_header',
			static function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$tab = isset( $_GET['tab'] ) ? sanitize_title_with_dashes( wp_unslash( $_GET['tab'] ) ) : '';
				if ( 'third-party' === $tab ) {
					echo '<p>' . esc_html__( 'These suggestions are based on Collections of Git Updater Update API Servers.', 'git-updater-collections' ) . '</p>';
				}
			}
		);

		// Add theme data.
		add_action( 'admin_enqueue_scripts', [ $this,'add_third_party_tab' ] );
		add_filter( 'themes_api_result', [ $this,'themes_api_result' ], 10, 3 );
	}

	/**
	 * Add 'Third Party' tab to 'Plugin > Add New'.
	 *
	 * @param array $tabs Array of plugin install tabs.
	 * @return array
	 */
	public function add_install_tab( $tabs ) {
		$tabs['third-party'] = esc_html_x( 'Third Party', 'Plugin Installer', 'git-updater-collections' );

		return $tabs;
	}

	/**
	 * Add args to plugins_api().
	 *
	 * @param array $args Array of arguments to plugins_api().
	 * @return array
	 */
	public function add_install_third_party_args( $args ) {
		$args = [
			'page'     => 1,
			'per_page' => 36,
			'locale'   => get_user_locale(),
			'browse'   => 'third-party',
		];

		return $args;
	}

	/**
	 * Modify plugins_api() response.
	 *
	 * @param stdClass $res    Object of results.
	 * @param string   $action Variable for plugins_api().
	 * @param stdClass $args   Object of plugins_api() args.
	 * @return stdClass
	 */
	public function plugins_api_result( $res, $action, $args ) {
		if ( property_exists( $args, 'browse' ) && 'third-party' === $args->browse ) {
			$response = wp_remote_post( home_url() . '/wp-json/git-updater/v1/update-api-additions/' );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {
				$res->info['count'] = 0;
				$res->plugins       = [];
				return $res;
			}
			$response     = json_decode( wp_remote_retrieve_body( $response ), true );
			$response     = array_filter(
				$response,
				function ( $item ) {
					return 'plugin' === $item['type'];
				}
			);
			$res->info    = [
				'page'    => 1,
				'pages'   => 1,
				'results' => count( $response ),
			];
			$res->plugins = $response;
		}

		return $res;
	}

	/**
	 * Add 'Third Party' tab in Add Themes page.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return void
	 */
	public function add_third_party_tab( $hook ) {
		if ( 'theme-install.php' === $hook ) {
			$tab_link = '<li><a href="#" data-sort="third-party">Third Party</a></li>';
			wp_add_inline_script(
				'theme', // The handle of Core's theme(.min).js file.
				"($ => $('.filter-links').append('{$tab_link}') )(jQuery);"
			);
		}
	}

	/**
	 * Modify themes_api() response.
	 *
	 * @param stdClass $res    Object of results.
	 * @param string   $action Variable for themes_api().
	 * @param stdClass $args   Object of themes_api() args.
	 * @return stdClass
	 */
	public function themes_api_result( $res, $action, $args ) {
		if ( ( property_exists( $args, 'browse' ) && 'third-party' === $args->browse )
			|| 'theme_information' === $action
		) {
			$response = wp_remote_post( home_url() . '/wp-json/git-updater/v1/update-api-additions/' );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {
				$res->info['count'] = 0;
				$res->themes        = [];
				return $res;
			}

			$response = json_decode( wp_remote_retrieve_body( $response ) ); // Do not convert object to associative array.
			$response = array_filter(
				(array) $response, // Convert the outer object to an array for now.
				function ( $item ) {
					return 'theme' === $item->type;
				}
			);

			// Fix some properties.
			$response = array_map(
				function ( $item ) {
					$item->author      = [ 'display_name' => $item->author ];
					$item->description = $item->sections->description;
					$item->preview_url = $item->preview_url ?? '';
					return $item;
				},
				$response
			);

			// Required for theme installation.
			if ( 'theme_information' === $action ) {
				$res->download_link = $response[ $res->slug ]->download_link;
				return $res;
			}

			$res->info   = [
				'page'    => 1,
				'pages'   => 1,
				'results' => count( $response ),
			];
			$res->themes = array_values( $response ); // Make it an object again.
		}

		return $res;
	}
}
