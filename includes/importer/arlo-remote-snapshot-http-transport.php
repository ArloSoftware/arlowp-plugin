<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

use ArloTraining\API\Transports\Transport;
use ArloTraining\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieves snapshot bytes using the WordPress HTTP API and approved remote-host policy.
 */
class RemoteSnapshotHttpTransport implements SnapshotHttpTransport {
	const TIMEOUT             = 20;
	const MAX_REDIRECTS       = 5;
	const SNAPSHOT_API_DOMAIN = 'arloapi.net';

	/** @var callable|null */
	private $host_address_resolver;

	/**
	 * Creates a transport with an optional deterministic DNS resolver.
	 *
	 * @param callable(string):array<int,string>|null $host_address_resolver Optional deterministic host resolver.
	 */
	public function __construct( ?callable $host_address_resolver = null ) {
		$this->host_address_resolver = $host_address_resolver;
	}

	/**
	 * Retrieves one bounded remote snapshot resource through the approved host policy.
	 *
	 * @param string $uri Snapshot resource URI.
	 * @param string $platform_hostname Platform hostname from the snapshot response.
	 * @param int    $max_response_bytes Maximum allowed response body bytes.
	 * @param int    $import_id Import identifier for diagnostics.
	 * @return string Response body.
	 */
	public function get_remote_content( string $uri, string $platform_hostname, int $max_response_bytes, int $import_id ): string {
		if ( $max_response_bytes < 1 ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot response maximum size is invalid' );
		}

		$current_url = $this->validate_snapshot_url( $uri );
		$ssl_verify  = $this->get_ssl_verify_setting();
		$redirects   = 0;

		while ( true ) {
			$current_url = $this->validate_snapshot_url( $current_url );
			$hostname    = $this->validate_remote_host( $current_url, $platform_hostname );
			$args        = [
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'httpversion'         => '1.1',
				'sslverify'           => $ssl_verify,
				'reject_unsafe_urls'  => true,
				'limit_response_size' => $max_response_bytes + 1,
				'headers'             => [ 'Referer' => $current_url ],
				'compress'            => true,
			];

			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Downloading snapshot data [host=' . $hostname . '; redirects=' . $redirects . '; timeout=' . self::TIMEOUT . 's; sslverify=' . ( $ssl_verify ? 'true' : 'false' ) . ']', $import_id );
			$response = wp_remote_get( $current_url, $args );
			if ( is_wp_error( $response ) ) {
				throw new \ArloTraining\PlatformAccessHttpException( $hostname . ': ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $this->is_redirect_response( $code ) ) {
				if ( $redirects >= self::MAX_REDIRECTS ) {
					throw new \ArloTraining\PlatformAccessHttpException( $hostname . ': Too many redirects' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
				}
				$current_url = $this->get_redirect_url( $response, $current_url, $hostname );
				$next_host   = wp_parse_url( $current_url, PHP_URL_HOST );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot data download was redirected [status=' . $code . '; from=' . $hostname . '; to=' . ( ! empty( $next_host ) ? $next_host : 'unknown' ) . ']', $import_id );
				++$redirects;
				continue;
			}

			if ( 200 === $code ) {
				$body = wp_remote_retrieve_body( $response );
				$this->validate_response_size( $response, $body, $max_response_bytes );
				if ( empty( $body ) ) {
					throw new \ArloTraining\PlatformAccessHttpException( 'Snapshot file download returned an empty response body' );
				}
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot data downloaded [host=' . $hostname . '; bytes=' . strlen( $body ) . '; redirects=' . $redirects . ']', $import_id );
				return $body;
			}

			throw new \ArloTraining\PlatformAccessHttpException( $hostname . ' returned ' . $code . ' ' . wp_remote_retrieve_response_message( $response ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
	}

	/**
	 * Resolves the configured TLS verification setting for WordPress HTTP requests.
	 *
	 * Verification is on unless an administrator ticks "Disable SSL verification
	 * for import". The option exists for environments that intercept TLS and
	 * applies only to snapshot downloads from allowlisted Arlo hosts.
	 *
	 * @return bool Whether WordPress HTTP requests should verify TLS certificates.
	 */
	private function get_ssl_verify_setting(): bool {
		$settings = get_option( 'arlo_settings', [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		return empty( $settings['disable_ssl_verification'] ) || $settings['disable_ssl_verification'] != 1; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Existing setting accepts the WordPress option's string or integer enabled value.
	}

	/**
	 * Validates a snapshot resource URI before any remote request occurs.
	 *
	 * @param mixed $url Snapshot resource URI.
	 * @return string Validated HTTP or HTTPS resource URI.
	 */
	private function validate_snapshot_url( $url ): string {
		if ( ! is_scalar( $url ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Invalid snapshot URI' );
		}
		$url    = (string) $url;
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Invalid snapshot URI scheme: ' . $scheme ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		return $url;
	}

	/**
	 * Validates the URI host against the admitted platform and public-address policy.
	 *
	 * @param string $url Snapshot resource URI.
	 * @param string $platform_hostname Platform hostname from the admitted snapshot response.
	 * @return string Validated normalized remote hostname.
	 */
	private function validate_remote_host( string $url, string $platform_hostname ): string {
		$host = $this->get_url_host( $url );
		if ( ! $this->is_allowed_snapshot_host( $host, $platform_hostname ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot URI host is not allowed' );
		}
		foreach ( $this->resolve_host_addresses( $host ) as $address ) {
			if ( ! $this->is_public_address( $address ) ) {
				throw new \ArloTraining\SnapshotProcessException( 'Snapshot URI host resolves to a disallowed network address' );
			}
		}
		return $host;
	}

	/**
	 * Extracts and normalizes a hostname from a validated resource URI.
	 *
	 * @param string $url Snapshot resource URI.
	 * @return string Normalized hostname from the URI.
	 */
	private function get_url_host( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) || ! is_string( $host ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Invalid snapshot URI host' );
		}
		return $this->normalize_hostname( $host );
	}

	/**
	 * Determines whether a normalized remote host is approved for snapshot retrieval.
	 *
	 * @param string $host Normalized remote hostname.
	 * @param string $platform_hostname Platform hostname from the admitted snapshot response.
	 * @return bool Whether the remote host is approved for snapshot retrieval.
	 */
	private function is_allowed_snapshot_host( string $host, string $platform_hostname ): bool {
		$platform_hostname = $this->validate_platform_hostname( $platform_hostname );
		$allowed           = $host === $platform_hostname || $this->host_matches_suffix( $host, self::SNAPSHOT_API_DOMAIN );

		/**
		 * Filters whether a snapshot host is allowed for an admitted platform.
		 *
		 * @param bool   $allowed           Whether the snapshot host is allowed.
		 * @param string $host              Normalized snapshot host.
		 * @param string $platform_hostname Normalized admitted platform hostname.
		 */
		return (bool) apply_filters( 'arlo_is_allowed_snapshot_host', $allowed, $host, $platform_hostname );
	}

	/**
	 * Normalizes and validates the platform hostname supplied by the admitted snapshot.
	 *
	 * @param string $platform_hostname Platform hostname from the admitted snapshot response.
	 * @return string Normalized validated platform hostname.
	 */
	private function validate_platform_hostname( string $platform_hostname ): string {
		$platform_hostname = $this->normalize_hostname( $platform_hostname );
		$platform_domain   = preg_quote( ltrim( Transport::PLATFORM_DOMAIN, '.' ), '/' );
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?\.' . $platform_domain . '$/i', $platform_hostname ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot platform hostname is invalid' );
		}
		return $platform_hostname;
	}

	/**
	 * Normalizes hostname casing, brackets, whitespace, and trailing dots.
	 *
	 * @param string $host Hostname input.
	 * @return string Normalized hostname.
	 */
	private function normalize_hostname( string $host ): string {
		return rtrim( strtolower( trim( trim( $host ), '[]' ) ), '.' );
	}

	/**
	 * Determines whether a host is a strict subdomain of an allowed suffix.
	 *
	 * @param string $host Normalized hostname.
	 * @param string $suffix Allowed domain suffix.
	 * @return bool Whether the hostname is a subdomain of the suffix.
	 */
	private function host_matches_suffix( string $host, string $suffix ): bool {
		return $host !== $suffix && substr( $host, -strlen( '.' . $suffix ) ) === '.' . $suffix;
	}

	/**
	 * Resolves a host and rejects malformed or unavailable address results.
	 *
	 * @param string $host Hostname to resolve.
	 * @return array<int,string> Validated IP addresses.
	 */
	protected function resolve_host_addresses( string $host ): array {
		if ( null !== $this->host_address_resolver ) {
			return $this->validate_host_addresses( call_user_func( $this->host_address_resolver, $host ) );
		}
		$filtered_addresses = $this->get_filtered_host_addresses( $host );
		if ( null !== $filtered_addresses ) {
			return $filtered_addresses;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}
		$addresses      = [];
		$ipv4_addresses = gethostbynamel( $host );
		if ( is_array( $ipv4_addresses ) ) {
			$addresses = array_merge( $addresses, $ipv4_addresses );
		}
		if ( function_exists( 'dns_get_record' ) ) {
			$records = dns_get_record( $host, DNS_A + DNS_AAAA );
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ip'] ) ) {
						$addresses[] = $record['ip'];
					}
					if ( ! empty( $record['ipv6'] ) ) {
						$addresses[] = $record['ipv6'];
					}
				}
			}
		}
		$addresses = array_unique( $addresses );
		if ( empty( $addresses ) ) {
			throw new \ArloTraining\PlatformAccessHttpException( $host . ': Could not resolve snapshot URI host' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
		return $addresses;
	}

	/**
	 * Retrieves filtered resolver addresses before the ordinary DNS lookup.
	 *
	 * @param string $host Hostname to resolve.
	 * @return array<int,string>|null Filtered addresses, when available.
	 */
	private function get_filtered_host_addresses( string $host ): ?array {
		$addresses = apply_filters( 'arlo_resolve_snapshot_host_addresses', null, $host );
		return null === $addresses ? null : $this->validate_host_addresses( $addresses );
	}

	/**
	 * Validates a DNS resolver result before public-address checks.
	 *
	 * @param mixed $addresses Candidate resolved addresses.
	 * @return array<int,string> Validated unique IP addresses.
	 */
	private function validate_host_addresses( $addresses ): array {
		if ( ! is_array( $addresses ) || empty( $addresses ) || array_keys( $addresses ) !== range( 0, count( $addresses ) - 1 ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot host resolver returned invalid addresses' );
		}
		$validated_addresses = [];
		foreach ( $addresses as $address ) {
			if ( ! is_scalar( $address ) ) {
				throw new \ArloTraining\SnapshotProcessException( 'Snapshot host resolver returned invalid addresses' );
			}
			$address = trim( (string) $address );
			if ( $address === '' || ! filter_var( $address, FILTER_VALIDATE_IP ) ) {
				throw new \ArloTraining\SnapshotProcessException( 'Snapshot host resolver returned invalid addresses' );
			}
			$validated_addresses[] = $address;
		}
		return array_values( array_unique( $validated_addresses ) );
	}

	/**
	 * Determines whether an HTTP response status requires redirect handling.
	 *
	 * @param int $code HTTP response status code.
	 * @return bool Whether the response requires redirect handling.
	 */
	private function is_redirect_response( int $code ): bool {
		return in_array( $code, [ 301, 302, 303, 307, 308 ], true );
	}

	/**
	 * Resolves and validates one HTTP redirect target.
	 *
	 * @param mixed  $response WordPress HTTP response.
	 * @param string $url Current snapshot resource URI.
	 * @param string $hostname Current normalized hostname.
	 * @return string Absolute redirect URI.
	 */
	private function get_redirect_url( $response, string $url, string $hostname ): string {
		$location = wp_remote_retrieve_header( $response, 'location' );
		if ( is_array( $location ) ) {
			$location = end( $location );
		}
		if ( ! is_scalar( $location ) || trim( (string) $location ) === '' ) {
			throw new \ArloTraining\PlatformAccessHttpException( $hostname . ' returned redirect without Location header' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}
		$location = trim( (string) $location );
		if ( wp_parse_url( $location, PHP_URL_SCHEME ) ) {
			return $location;
		}
		if ( class_exists( 'WP_Http' ) ) {
			return \WP_Http::make_absolute_url( $location, $url );
		}
		throw new \ArloTraining\PlatformAccessHttpException( $hostname . ' returned relative redirect that could not be resolved' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
	}

	/**
	 * Determines whether an IP address is public and non-reserved.
	 *
	 * @param string $address Resolved IP address.
	 * @return bool Whether the address is public and non-reserved.
	 */
	private function is_public_address( string $address ): bool {
		return (bool) filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Rejects a response whose declared or actual body exceeds its caller-provided limit.
	 *
	 * @param mixed  $response WordPress HTTP response.
	 * @param string $body Response body.
	 * @param int    $max_response_bytes Maximum allowed response body bytes.
	 * @return void
	 */
	private function validate_response_size( $response, string $body, int $max_response_bytes ): void {
		$content_length = wp_remote_retrieve_header( $response, 'content-length' );
		if ( is_array( $content_length ) ) {
			$content_length = end( $content_length );
		}
		if ( ( is_numeric( $content_length ) && (int) $content_length > $max_response_bytes ) || strlen( $body ) > $max_response_bytes ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot file download exceeds maximum allowed size' );
		}
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
