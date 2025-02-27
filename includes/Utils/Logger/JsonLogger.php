<?php
/**
 * Json Logger
 *
 * @package Reepay\Checkout\Utils\Logger
 */

namespace Reepay\Checkout\Utils\Logger;

use DateTime;
use WP_Filesystem_Base;

/**
 * Class
 *
 * @package Reepay\Checkout\Utils\Logger
 */
class JsonLogger {
	/**
	 * Wp Filesystem
	 *
	 * @var WP_Filesystem_Base $wp_filesystem
	 */
	private WP_Filesystem_Base $wp_filesystem;

	/**
	 * Directory path logs
	 *
	 * @var string $directory_path
	 */
	private string $directory_path;

	/**
	 * Source log.
	 *
	 * @var string $source
	 */
	private string $source = 'billwerk';

	/**
	 * Classes to ignore in backtrace
	 *
	 * @var array $ignore_classes_backtrace class names.
	 */
	private array $ignore_classes_backtrace = array();

	/**
	 * Class constructor
	 *
	 * @param string $directory_path Directory path logs.
	 * @param string $source Source log.
	 */
	public function __construct( string $directory_path, string $source ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		$this->wp_filesystem  = $wp_filesystem;
		$this->directory_path = $directory_path;
		$this->source         = $source;
	}

	/**
	 * Source log.
	 *
	 * @param string $source Source log.
	 *
	 * @return self
	 */
	public function set_source( string $source ): self {
		$this->source = $source;
		return $this;
	}

	/**
	 * Create directory logs
	 *
	 * @param string $log_directory log directory path.
	 *
	 * @return void
	 */
	public function create_nested_directories( string $log_directory ) {
		$path_parts = explode( '/', $log_directory );
		$path       = '';

		foreach ( $path_parts as $part ) {
			if ( ! empty( $part ) ) {
				$path .= '/' . $part;
				if ( ! $this->wp_filesystem->is_dir( $path ) ) {
					$this->wp_filesystem->mkdir( $path, FS_CHMOD_DIR );
				}
			}
		}
	}

	/**
	 * Get absolute path log file
	 *
	 * @return string
	 */
	private function get_log_file_path(): string {
		$date          = new DateTime();
		$year          = $date->format( 'Y' );
		$month         = $date->format( 'm' );
		$day           = $date->format( 'd' );
		$log_directory = "{$this->directory_path}/{$year}/{$month}/{$day}";

		if ( ! $this->wp_filesystem->is_dir( $log_directory ) ) {
			$this->create_nested_directories( $log_directory );
		}

		return "{$log_directory}/{$this->source}.json";
	}

	/**
	 * Add ignored classes to backtrace
	 *
	 * @param array $names class names.
	 *
	 * @return self
	 */
	public function add_ignored_classes_backtrace( array $names ): self {
		$this->ignore_classes_backtrace = array( ...$this->ignore_classes_backtrace, ...$names );
		return $this;
	}

	/**
	 * Get log backtrace
	 *
	 * @return array
	 */
	private function get_backtrace(): array {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		$ignore_classes = array( self::class, ...$this->ignore_classes_backtrace );

		$backtrace = array_filter(
			$backtrace,
			function ( $trace ) use ( $ignore_classes ) {
				return ! ( isset( $trace['class'] ) && in_array( $trace['class'], $ignore_classes, true ) );
			}
		);

		return array_values( $backtrace );
	}

	/**
	 * Logging to file
	 *
	 * @param string                 $level level log.
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	private function log( string $level, $message, array $context = array() ) {
		$log_entry = array(
			'timestamp' => gmdate( 'c' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'backtrace' => $this->get_backtrace(),
		);

		$log_file_path = $this->get_log_file_path();

		if ( $this->wp_filesystem->exists( $log_file_path ) ) {
			$log_content = $this->wp_filesystem->get_contents( $log_file_path );
			$log_array   = json_decode( $log_content, true );
			if ( ! is_array( $log_array ) ) {
				$log_array = array();
			}
		} else {
			$log_array = array();
		}

		$log_array[] = $log_entry;

		$log_entry_json = wp_json_encode( $log_array );
		$this->wp_filesystem->put_contents( $log_file_path, $log_entry_json, FS_CHMOD_FILE );
	}

	/**
	 * Adds info level message.
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function info( $message, array $context = array() ) {
		$this->log( LevelLogger::INFO, $message, $context );
	}

	/**
	 * Adds debug level message.
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function debug( $message, array $context = array() ) {
		$this->log( LevelLogger::DEBUG, $message, $context );
	}

	/**
	 * Adds error level message.
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function error( $message, array $context = array() ) {
		$this->log( LevelLogger::ERROR, $message, $context );
	}

	/**
	 * Adds error level message.
	 *
	 * @param string|array|int|float $message message log.
	 * @param array                  $context context log.
	 *
	 * @return void
	 */
	public function warning( $message, array $context = array() ) {
		$this->log( LevelLogger::WARNING, $message, $context );
	}
}
