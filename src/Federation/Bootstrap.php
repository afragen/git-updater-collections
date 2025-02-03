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
 * Class Bootstrap
 */
class Bootstrap {
	/**
	 * Holds main plugin file.
	 *
	 * @var string
	 */
	protected $file;

	/**
	 * Holds main plugin directory.
	 *
	 * @var string
	 */
	protected $dir;

	/**
	 * Constructor.
	 *
	 * @param  string $file Main plugin file.
	 * @return void
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$this->dir  = dirname( $file );
	}

	/**
	 * Run the bootstrap.
	 *
	 * @return bool|void
	 */
	public function run() {
		new Init();
		( new Settings() )->load_hooks();
		register_deactivation_hook( $this->file, [ new Federation(), 'blast_cache_on_deactivate' ] );
	}
}
