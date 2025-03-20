<?php

namespace WpImageOptimizer\Utility;

class ProgressManager {
	private const TRANSIENT_PREFIX = 'wp_image_optimizer_progress_';
	private const EXPIRATION       = 3600; // 1 hour

	/**
	 * Start a new progress tracking process
	 */
	public function startProcess( string $processId, int $total ): void {
		set_transient(
			self::TRANSIENT_PREFIX . $processId,
			array(
				'total'     => $total,
				'processed' => 0,
				'started'   => time(),
				'updated'   => time(),
			),
			self::EXPIRATION
		);
	}

	/**
	 * Update progress for a process
	 */
	public function updateProgress( string $processId, int $processed ): void {
		$progress = get_transient( self::TRANSIENT_PREFIX . $processId );
		if ( ! $progress ) {
			return;
		}

		$progress['processed'] = $processed;
		$progress['updated']   = time();

		set_transient( self::TRANSIENT_PREFIX . $processId, $progress, self::EXPIRATION );
	}

	/**
	 * Get progress for a process
	 */
	public function getProgress( string $processId ): ?array {
		$progress = get_transient( self::TRANSIENT_PREFIX . $processId );
		if ( ! $progress ) {
			return null;
		}

		$progress['percentage'] = ( $progress['total'] > 0 )
			? round( ( $progress['processed'] / $progress['total'] ) * 100 )
			: 0;

		return $progress;
	}

	/**
	 * Clear progress for a process
	 */
	public function clearProgress( string $processId ): void {
		delete_transient( self::TRANSIENT_PREFIX . $processId );
	}
}
