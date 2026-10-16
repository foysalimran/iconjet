<?php
/**
 * WordPress test suite configuration.
 *
 * Defaults target the Local (localwp.com) site this plugin is developed in.
 * Override with environment variables in other environments.
 *
 * WARNING: the test suite drops and recreates every table in DB_NAME.
 * Never point it at a real site database.
 *
 * @package Iconjet
 */

define( 'ABSPATH', getenv( 'ICONJET_TEST_ABSPATH' ) ?: dirname( __DIR__, 4 ) . '/' );

define( 'DB_NAME', getenv( 'ICONJET_TEST_DB_NAME' ) ?: 'iconjet_tests' );
define( 'DB_USER', getenv( 'ICONJET_TEST_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'ICONJET_TEST_DB_PASSWORD' ) ?: 'root' );
define( 'DB_HOST', getenv( 'ICONJET_TEST_DB_HOST' ) ?: 'localhost:' . getenv( 'HOME' ) . '/Library/Application Support/Local/run/IW-2l785k/mysql/mysqld.sock' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
