<?php
/**
 * Git Updater - Collections.
 * Requires Git Updater plugin.
 *
 * @package git-updater-collections
 * @author  Andy Fragen
 * @link    https://github.com/afragen/git-updater-collections
 * @link    https://github.com/afragen/github-updater
 */

/**
 * Plugin Name:       Git Updater - Collections
 * Plugin URI:        https://github.com/afragen/git-updater-collections
 * Description:       Federate with other Git Updater Update API servers.
 * Version:           0.4.0
 * Author:            Andy Fragen
 * License:           MIT
 * Network:           true
 * Domain Path:       /languages
 * Text Domain:       git-updater-collections
 * GitHub Plugin URI: https://github.com/afragen/git-updater-collections
 * xGitHub Languages:  https://github.com/afragen/git-updater-collections-translations
 * Primary Branch:    main
 * Requires at least: 6.6
 * Requires PHP:      8.0
 */

namespace Fragen\Git_Updater\Collections;

/*
 * Exit if called directly.
 * PHP version check and exit.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load Autoloader.
require_once __DIR__ . '/vendor/autoload.php';

add_action(
	'init',
	function () {
		( new Bootstrap( __FILE__ ) )->run();
	}
);

add_filter(
	'gu_additions',
	static function ( $listing, $repos, $type ) {
		return ( new Collections() )->load_additions( $listing, $repos, $type );
	},
	10,
	3
);
