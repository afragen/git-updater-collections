<?php
/**
 * Git Updater
 *
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/git-updater
 * @package   git-updater
 */

namespace Fragen\Git_Updater\Collections;

use Fragen\Git_Updater\Additions\Additions;

/**
 * Class Collections
 *
 * Add federated repos and/or remove defederated repos in Git Updater Additions.
 */
class Collections {
	use \Fragen\Git_Updater\Traits\GU_Trait;

	// phpcs:disable Generic.Commenting.DocComment.MissingShort
	/** @var string */
	protected static $rest_endpoint = 'wp-json/git-updater/v1/get-additions-data/';

	/** @var array */
	protected static $additions;

	/** @var array */
	protected static $options;

	/** @var array */
	protected static $collections = [];

	/** @var array */
	protected $response;
	// phpcs:enable

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$additions = get_site_option( 'git_updater_additions', [] );
		self::$options   = get_site_option( 'git_updater_collections', [] );
	}

	/**
	 * Start it up.
	 *
	 * @param string $type (plugin|theme).
	 *
	 * @return void
	 */
	public function run( $type ) {
		$additions = [];
		foreach ( self::$options as $option ) {
			$collection        = [];
			$collection        = $this->get_additions_data( $option['uri'] );
			$additions         = array_merge( $additions, $collection );
			self::$collections = array_merge( self::$collections, $additions );
			continue;
		}

		$additions = array_filter(
			$additions,
			function ( $item ) use ( $type ) {
				return str_contains( $item['type'], $type );
			}
		);

		$this->set_repo_cache( "git_updater_repository_add_{$type}", $additions, "git_updater_repository_add_{$type}" );

		self::$additions = array_merge( self::$additions, $additions );
		self::$additions = array_map( 'unserialize', array_unique( array_map( 'serialize', self::$additions ) ) );

		$this->unique_packages();
	}

	/**
	 * Load additions from gu_additions hook.
	 *
	 * @param array  $listing Array of previous additions.
	 * @param array  $repos   Repository collection.
	 * @param string $type    (plugin|theme).
	 *
	 * @return array
	 */
	public function load_additions( $listing, $repos, $type ) {
		$this->run( $type );
		$config    = $this->get_additions_cache( $type );
		$additions = new Additions();
		$additions->register( $config, $repos, $type );

		return $additions->add_to_git_updater;
	}

	/**
	 * Get repository additions cache.
	 *
	 * @param string $type (plugin|theme).
	 *
	 * @return array
	 */
	public function get_additions_cache( $type ) {
		$additions_obj = new Additions();
		$config        = $this->get_repo_cache( "git_updater_repository_add_{$type}" );
		$config        = $config ? $config[ "git_updater_repository_add_{$type}" ] : [];
		$config        = array_merge( $config, self::$additions );

		if ( ! $config ) {
			$config = get_site_option( 'git_updater_additions', [] );
			foreach ( $config as $key => $addition ) {
				if ( ! str_contains( $addition['type'], $type ) ) {
					unset( $config[ $key ] );
				}
			}
		}
		$config = $additions_obj->deduplicate( $config );

		return $config;
	}

	/**
	 * Get REST API additions data.
	 *
	 * @param string $uri URI of collection server.
	 *
	 * @return array
	 */
	private function get_additions_data( string $uri ) {
		$response = $this->get_repo_cache( $uri );
		$response = $response ? $response[ $uri ] : $response;
		if ( ! $response ) {
			$endpoint = trailingslashit( $uri ) . self::$rest_endpoint;
			$response = wp_remote_post( $endpoint );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) || is_wp_error( $response ) ) {
				return [];
			}
			$response = (array) json_decode( wp_remote_retrieve_body( $response ), true );
			foreach ( array_keys( $response ) as $key ) {
				$response[ $key ]['source'] = md5( $uri );
			}
			$this->set_repo_cache( $uri, (array) $response, $uri, '+3 days' );
		}

		return (array) $response;
	}

	/**
	 * Empty single cache on collection removal.
	 *
	 * @param string $uri_hash MD5 hash of URI.
	 *
	 * @return void
	 */
	public function blast_single_cache( $uri_hash ) {
		$options = get_site_option( 'git_updater_collections' );
		foreach ( $options as $option ) {
			if ( $uri_hash === $option['ID'] ) {
				$this->set_repo_cache( $option['uri'], false, $option['uri'] );
				$this->delete_cached_data( $option['ID'] );
				$this->set_repo_cache( 'git_updater_repository_add_plugin', false, 'git_updater_repository_add_plugin' );
				$this->set_repo_cache( 'git_updater_repository_add_theme', false, 'git_updater_repository_add_theme' );
			}
			foreach ( self::$additions as $key => $addition ) {
				if ( $addition['source'] === $uri_hash ) {
					unset( self::$additions[ $key ] );
				}
			}
		}
		$this->delete_cached_data( md5( 'plugin' ) );
		$this->delete_cached_data( md5( 'theme' ) );

		update_site_option( 'git_updater_additions', self::$additions );
	}

	/**
	 * Blast caches.
	 *
	 * @return void
	 */
	public function blast_all_caches() {
		foreach ( self::$options as $collection ) {
			$this->blast_single_cache( $collection['ID'] );
		}
	}

	/**
	 * Delete Collections `ghu-` prefixed data from options table.
	 *
	 * @param string $cache_key MD5 hash of cache ID.
	 *
	 * @return bool
	 */
	public function delete_cached_data( $cache_key ) {
		global $wpdb;

		$table         = is_multisite() ? $wpdb->base_prefix . 'sitemeta' : $wpdb->base_prefix . 'options';
		$column        = is_multisite() ? 'meta_key' : 'option_name';
		$delete_string = 'DELETE FROM ' . $table . ' WHERE ' . $column . ' LIKE %s LIMIT 1000';

		$wpdb->query( $wpdb->prepare( $delete_string, [ "%ghu-{$cache_key}%" ] ) ); // phpcs:ignore

		return true;
	}

	/**
	 * Compile unique collections to site option.
	 *
	 * @return void
	 */
	protected function unique_packages() {
		foreach ( self::$options as $repo ) {
			$add_repo          = $this->get_repo_cache( $repo['uri'] );
			$add_repo          = $add_repo ? ( $add_repo[ $repo['uri'] ] ?: [] ) : [];
			self::$collections = array_merge( self::$collections, $add_repo );
		}

		foreach ( self::$additions as $key_add => $addition ) {
			foreach ( self::$collections as $key_col => $collection ) {
				if ( ! isset( $collection['source'] ) ) {
					break;
				}
				if ( $addition['ID'] === $collection['ID'] && $addition['source'] === $collection['source'] ) {
					unset( self::$collections[ $key_col ], self::$additions[ $key_add ] );
					break;
				}
			}
		}
		self::$collections = ( new Additions() )->deduplicate( self::$collections );
		self::$additions   = array_merge( self::$additions, self::$collections );
		update_site_option( 'git_updater_additions', self::$additions );
	}
}
