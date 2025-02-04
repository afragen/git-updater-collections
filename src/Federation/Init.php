<?php
/**
 * Git Updater Federation
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater-federation
 * @package   git-updater-federation
 */

namespace Fragen\Git_Updater\Federation;

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
					echo '<p>' . esc_html__( 'These suggestions are based on Git Updater API servers.', 'git-updater-federation' ) . '</p>';
				}
			}
		);
	}

	/**
	 * Add 'Third Party' tab to 'Plugin > Add New'.
	 *
	 * @param array $tabs Array of plugin install tabs.
	 * @return array
	 */
	public function add_install_tab( $tabs ) {
		$tabs['third-party'] = esc_html_x( 'Third Party', 'Plugin Installer', 'git-updater-federation' );

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
}
