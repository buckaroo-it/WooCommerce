<?php
require_once __DIR__ . '/Buckaroo_Migration.php';
require_once __DIR__ . '/Buckaroo_Migration_Exception.php';
/**
 * Core class for handling migrations
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */

class Buckaroo_Migration_Handler {


	/**
	 * Version in database
	 *
	 * @var string
	 */
	protected $databaseVersion;

	/**
	 * Register hooks
	 */
	public function __construct() {
		$this->databaseVersion = WC_Buckaroo_Install::get_db_version();
		add_action(
			'plugins_loaded',
			array( $this, 'run_any_migrations' )
		);
		add_action(
			'upgrader_process_complete',
			array( $this, 'plugin_update_complete' ),
			10,
			2
		);
	}
	/**
	 * Main function that does the updating / rollback
	 *
	 * @return void
	 */
	public function handle() {
		$migrationStatus = $this->get_migration_status();
		// no need to migrate
		if ( $migrationStatus === 0 ) {
			return;
		}
		$this->copy_language_files();

		if ( $migrationStatus === -1 ) {
			return $this->update();
		}
		if ( $migrationStatus === 1 ) {
			set_transient(
				get_current_user_id() . 'buckarooAdminNotice',
				array(
					'type'    => 'warning',
					'message' => __(
						'You installed a previous version of Buckaroo BPE, some functionality may not work properly',
						'wc-buckaroo-bpe-gateway'
					),
				)
			);
		}
	}
	/**
	 * Check if plugin needs migrating
	 *
	 * @return void
	 */
	protected function get_migration_status() {
		return version_compare(
			$this->databaseVersion,
			BuckarooConfig::VERSION
		);
	}
	/**
	 * Update plugin
	 *
	 * @return void
	 */
	protected function update() {
		$migrations = array_filter(
			$this->get_migration_items(),
			function ( $migration ) {
				return $this->compare_versions(
					$migration['version'],
					BuckarooConfig::VERSION,
					'<='
				) &&
				$this->compare_versions(
					$migration['version'],
					$this->databaseVersion,
					'>'
				);
			}
		);
		$this->execute_list(
			$migrations
		);
		WC_Buckaroo_Install::set_db_version( BuckarooConfig::VERSION );
	}
	/**
	 * Load migrations
	 *
	 * @param array $migrations
	 *
	 * @return array
	 */
	protected function execute_list( $migrations ) {
		$migrationObjects = array();
		foreach ( $migrations as $migration ) {
			if ( file_exists( $migration['path'] ) ) {
				$object = include_once $migration['path'];
				$this->execute(
					$object,
					$migration['version']
				);
			}
		}

		return $migrationObjects;
	}
	/**
	 * Execute single migration method
	 *
	 * @param mixed  $migration
	 * @param string $method
	 *
	 * @return void
	 */
	protected function execute( $migration, $version ) {
		try {
			if (
				$migration instanceof Buckaroo_Migration &&
				method_exists( $migration, 'execute' )
			) {
				$migration->execute();
			}
		} catch ( \Throwable $th ) {
			throw new Buckaroo_Migration_Exception(
				'Cannot run migration for version: ' . $version,
				1,
				$th
			);

		}
	}
	/**
	 * Get all migration items
	 *
	 * @return array
	 */
	protected function get_migration_items() {
		$directory = realpath( __DIR__ . '/list' );

		$migrations = glob( $directory . '/migration-*.php' );
		return array_map(
			function ( $migration ) {
				$version = str_replace(
					'migration-',
					'',
					basename( $migration )
				);

				$version = str_replace(
					'.php',
					'',
					$version
				);

				return array(
					'version' => $version,
					'path'    => $migration,
				);
			},
			$migrations
		);
	}
	/**
	 * Compare 2 versions using the operator
	 *
	 * @param string $version1
	 * @param string $version2
	 * @param string $operator
	 *
	 * @return boolean
	 */
	protected function compare_versions( $version1, $version2, $operator ) {
		return version_compare( $version1, $version2, $operator );
	}
	/**
	 * Check if plugin was updated transient and execute any migration,
	 * function runned by `plugins_loaded` hook
	 *
	 * @return void
	 */
	public function run_any_migrations() {
		$this->databaseVersion = WC_Buckaroo_Install::get_db_version();

		// don't update if plugin is not installed
		if ( ! WC_Buckaroo_Install::isInstalled() ) {
			delete_transient( 'buckaroo_plugin_updated' );
		}

		if ( get_transient( 'buckaroo_plugin_updated' ) ) {
			try {
				$this->handle();
				delete_transient( 'buckaroo_plugin_updated' );
			} catch ( Buckaroo_Migration_Exception $e ) {
				set_transient(
					get_current_user_id() . 'buckarooAdminNotice',
					array(
						'type'    => 'error',
						'message' => 'Buckaroo: ' . $e->getMessage(),
					)
				);
				Buckaroo_Logger::log( __METHOD__, $e );
			} catch ( \Throwable $th ) {
				Buckaroo_Logger::log( __METHOD__, $th );
			}
		}
	}
	/**
	 * Copy updated language files
	 *
	 * @return void
	 */
	protected function copy_language_files() {
		foreach ( glob( dirname( BK_PLUGIN_FILE ) . '/languages/*.{po,mo}', GLOB_BRACE ) as $file ) {
			if ( ! is_dir( $file ) && is_readable( $file ) ) {

				$dest = WP_CONTENT_DIR . '/languages/plugins/';

				if ( ! file_exists( $dest ) && ! is_dir( $dest ) ) {
					mkdir( $dest, 0755, true );
				}
				copy( $file, $dest . basename( $file ) );
			}
		}
	}
	/**
	 * Set transient `buckaroo_plugin_updated` on hook `upgrader_process_complete`
	 *
	 * @param array $upgrader_object
	 * @param array $options
	 *
	 * @return void
	 */
	public function plugin_update_complete( $upgrader_object, $options ) {
		// The path to our plugin's main file
		$our_plugin = plugin_basename( BK_PLUGIN_FILE );
		// If an update has taken place and the updated type is plugins and the plugins element exists
		if ( $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
			// Iterate through the plugins being updated and check if ours is there
			foreach ( $options['plugins'] as $plugin ) {
				if ( $plugin == $our_plugin ) {
					// Set a transient to record that our plugin has just been updated
					set_transient( 'buckaroo_plugin_updated', 1 );
				}
			}
		}
	}
}
