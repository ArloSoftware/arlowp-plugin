<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established provisioning file follows the arlo-* loader convention.

namespace ArloTraining\Provisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Immutable outcome of comparing the live plugin tables with the canonical schema.
 *
 * Separates "the schema differs" from "the schema could not be read", because only the
 * former justifies a destructive repair.
 */
final class SchemaInspection {

	/** The live schema hash equals the canonical hash. */
	const STATUS_MATCHES = 'matches';

	/** The live schema was read successfully and differs from the canonical hash. */
	const STATUS_MISMATCH = 'mismatch';

	/** A database read failed, so nothing is known about the live schema. */
	const STATUS_UNREADABLE = 'unreadable';

	/** @var string */
	private $status;

	/** @var string|null */
	private $hash;

	/** @var list<string> */
	private $missing_tables;

	/** @var string */
	private $reason;

	/**
	 * @param string       $status         One of the STATUS_* constants.
	 * @param string|null  $hash           Live schema hash, or null when unreadable.
	 * @param list<string> $missing_tables Owned tables absent from the live database.
	 * @param string       $reason         Diagnostic text for an unreadable schema.
	 */
	private function __construct( string $status, ?string $hash, array $missing_tables, string $reason ) {
		$this->status         = $status;
		$this->hash           = $hash;
		$this->missing_tables = $missing_tables;
		$this->reason         = $reason;
	}

	/**
	 * Builds the outcome of a successful read.
	 *
	 * @param string       $live_hash      Hash computed from the live schema.
	 * @param string       $expected_hash  Canonical schema hash.
	 * @param list<string> $missing_tables Owned tables absent from the live database.
	 * @return self
	 */
	public static function from_hash( string $live_hash, string $expected_hash, array $missing_tables = [] ): self {
		$status = $live_hash === $expected_hash ? self::STATUS_MATCHES : self::STATUS_MISMATCH;

		return new self( $status, $live_hash, $missing_tables, '' );
	}

	/**
	 * Builds the outcome of a failed read.
	 *
	 * @param string $reason Diagnostic text describing the failed read.
	 * @return self
	 */
	public static function unreadable( string $reason ): self {
		return new self( self::STATUS_UNREADABLE, null, [], $reason );
	}

	/**
	 * @return string One of the STATUS_* constants.
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * @return string|null Live schema hash, or null when the schema could not be read.
	 */
	public function get_hash(): ?string {
		return $this->hash;
	}

	/**
	 * @return list<string> Owned tables absent from the live database.
	 */
	public function get_missing_tables(): array {
		return $this->missing_tables;
	}

	/**
	 * @return string Diagnostic text for an unreadable schema; empty otherwise.
	 */
	public function get_reason(): string {
		return $this->reason;
	}

	/**
	 * @return bool True when the live schema was read and can be trusted.
	 */
	public function is_readable(): bool {
		return self::STATUS_UNREADABLE !== $this->status;
	}

	/**
	 * @return bool True only when the schema was read successfully and differs from the canonical one.
	 */
	public function needs_repair(): bool {
		return self::STATUS_MISMATCH === $this->status;
	}
}
