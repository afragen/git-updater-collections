<?php
/**
 * Git Updater - Federation.
 * Requires Git Updater plugin.
 *
 * @package git-updater-federation
 * @author  Andy Fragen
 * @link    https://github.com/afragen/git-updater-federation
 * @link    https://github.com/afragen/github-updater
 */

/**
 * Plugin Name:       Git Updater - Federation
 * Plugin URI:        https://github.com/afragen/git-updater-federation
 * Description:       Federate with other Git Updater Update API servers.
 * Version:           0.1.0
 * Author:            Andy Fragen
 * License:           MIT
 * Network:           true
 * Domain Path:       /languages
 * Text Domain:       git-updater-federation
 * GitHub Plugin URI: https://github.com/afragen/git-updater-federation
 * xGitHub Languages:  https://github.com/afragen/git-updater-federation-translations
 * Primary Branch:    main
 * Requires at least: 6.6
 * Requires PHP:      8.0
 */

namespace Fragen\Git_Updater\Federation;

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
