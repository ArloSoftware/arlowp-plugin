<?php
namespace ArloTraining\API\Transports;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// load main Transport class for extending
require_once 'Transport.php';

// now use it
use ArloTraining\API\Transports\Transport;
use ArloTraining\CacheControl;

class Wordpress extends Transport
{
	/**
	 * Dispatch a GET or POST request to the Arlo platform API and return the decoded JSON response.
	 *
	 * GET requests are served from the object cache when available. POST requests are never cached.
	 *
	 * @param string      $platform_name  Arlo platform subdomain (e.g. "acme").
	 * @param string      $path           API path, including any query string.
	 * @param mixed|null  $post_data      POST body data; null for GET requests.
	 * @param bool        $public         Whether to use the public API endpoint.
	 * @param string      $plugin_version Plugin version string sent as X-Plugin-Version header.
	 * @param bool        $force_ssl      Whether to force HTTPS.
	 *
	 * @return mixed|null Decoded JSON value (object, array, scalar), or null for empty responses.
	 *
	 * @throws \ArloTraining\PlatformAccessHttpException On HTTP error, WAF challenge, unexpected
	 *                                                   Content-Type, or JSON decode failure.
	 */
	public function request($platform_name, $path, $post_data=null, $public=true, $plugin_version = '', $force_ssl = true) {
		// HTTP POST requests must not be cached.
		if ( $post_data !== null ) {
			return $this->executeHttpJsonRequest($platform_name, $path, $post_data, $public, $plugin_version, $force_ssl);
		}

		$cache_key = md5(serialize(func_get_args()));

		return CacheControl::get_cached_object($cache_key, function() use ($platform_name, $path, $post_data, $public, $plugin_version, $force_ssl) {
			return $this->executeHttpJsonRequest($platform_name, $path, $post_data, $public, $plugin_version, $force_ssl);
		}, 'ArloAPI', $this->getCacheTime());
	}

	private function executeHttpJsonRequest($platform_name, $path, $post_data, $public, $plugin_version, $force_ssl) {
		$url = $this->getRemoteURL($platform_name, $public, $force_ssl) . $path;
		$host = wp_parse_url($url, PHP_URL_HOST) ?: $platform_name . Transport::PLATFORM_DOMAIN;

		$args = array(
			'headers' => array(
				'X-Plugin-Version' => $plugin_version,
				'Accept' => 'application/json',
			),
			'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ArloPlugin/' . $plugin_version . '; ' . get_bloginfo( 'url' ),
			'httpversion' => '1.1',
			'stream'      => false,
			'timeout'     => $this->getRequestTimeout(),
			'method'      => ($post_data === null) ? 'GET' : 'POST',
		);

		if ( $post_data !== null ) {
			$request_body = wp_json_encode($post_data);

			if ( $request_body === false ) {
				throw new \ArloTraining\PlatformAccessHttpException($host . ' request body could not be encoded as JSON.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}

			$args['headers']['Content-Type'] = 'application/json';
			$args['headers']['Expect']       = ''; // Suppress 100-continue handshake.
			$args['body']                    = $request_body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error($response) ) {
			throw new \ArloTraining\PlatformAccessHttpException($host . ': ' . $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$response_code    = (int) wp_remote_retrieve_response_code($response);
		$response_message = (string) wp_remote_retrieve_response_message($response);

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_detail = $this->bodyExcerpt(wp_remote_retrieve_body($response));
			throw new \ArloTraining\PlatformAccessHttpException($host . ' returned ' . $response_code . ' ' . $response_message . ($error_detail ? ': ' . $error_detail : '')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		// AWS ALB WAF Challenge returns 202 with x-amzn-waf-action: challenge — not a valid API response.
		$waf_action = wp_remote_retrieve_header( $response, 'x-amzn-waf-action' );
		if ( is_array( $waf_action ) ) {
			$waf_action = end( $waf_action );
		}
		if ( strtolower( (string) $waf_action ) === 'challenge' ) {
			throw new \ArloTraining\PlatformAccessHttpException($host . ' returned an AWS WAF Challenge response. The request was blocked by a web application firewall.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$body = wp_remote_retrieve_body($response);

		$content_type_raw   = wp_remote_retrieve_header( $response, 'content-type' );
		$content_type       = is_array( $content_type_raw ) ? implode( ', ', $content_type_raw ) : (string) $content_type_raw;
		$content_type_parse = is_array( $content_type_raw ) ? (string) end( $content_type_raw ) : (string) $content_type_raw;
		$media_type         = strtolower( trim( explode( ';', $content_type_parse, 2 )[0] ) );

		// Validate Content-Type before the empty-body bail so that an explicit non-JSON
		// Content-Type (e.g. text/html) is always rejected, even on an empty body.
		// An absent Content-Type on an empty body is allowed (e.g. 204 No Content).
		if ( $media_type !== 'application/json' && substr( $media_type, -5 ) !== '+json'
			&& ( $body !== '' || $media_type !== '' ) ) {
			$error_detail = $this->bodyExcerpt( $body );
			throw new \ArloTraining\PlatformAccessHttpException(
				$host . ' returned an unexpected Content-Type (' . $content_type . ')' . ( $error_detail ? ': ' . $error_detail : '' ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			);
		}

		if ( $body === '' ) {
			return null;
		}

		$decoded_response = json_decode($body);

		if ( $decoded_response === null && json_last_error() !== JSON_ERROR_NONE ) {
			throw new \ArloTraining\PlatformAccessHttpException(
				$host . ' returned an invalid JSON response: ' . $this->bodyExcerpt($body) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			);
		}

		return $decoded_response;
	}

	private function bodyExcerpt(string $body): string {
		return substr(wp_strip_all_tags($body), 0, 500);
	}
}
