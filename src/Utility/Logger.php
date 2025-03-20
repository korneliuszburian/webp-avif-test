<?php

namespace WpImageOptimizer\Utility;

class Logger {
	private const LOG_OPTION = 'wp_image_optimizer_logs';
	private const MAX_LOGS   = 100;

	/**
	 * Log an info message
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log an error message
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log a warning message
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log a message
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		$logs = get_option( self::LOG_OPTION, array() );

		// Add new log entry
		$logs[] = array(
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'timestamp' => time(),
		);

		// Limit log size
		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, -self::MAX_LOGS );
		}

		update_option( self::LOG_OPTION, $logs );
	}

	/**
	 * Get all logs
	 */
	public function getLogs(): array {
		return get_option( self::LOG_OPTION, array() );
	}

	/**
	 * Clear all logs
	 */
	public function clearLogs(): void {
		delete_option( self::LOG_OPTION );
	}
}
