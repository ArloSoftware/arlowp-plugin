<?php

namespace ArloTraining\Importer;

use ArloTraining\Crypto;
use ArloTraining\API\Transports\Transport;

class Download extends BaseImporter  {
	const TIMEOUT = 20;
	const MAX_REDIRECTS = 5;
	const SNAPSHOT_API_DOMAIN = 'arloapi.net';
	const RESPONSE_SIZE_BUFFER_BYTES = 1048576;
	const MAX_RESPONSE_BYTES = ImportRequest::FRAGMENT_MAX_BYTE_SIZE + self::RESPONSE_SIZE_BUFFER_BYTES;

	public $import_part;
	public $import_iteration;
	public $uri;
	public $response_json;


	protected function save_entity($item) {}

	public function run() {
		
		if (!empty($this->uri)) {
			$content = $this->get_remote_data($this->uri);

			$key = $this->response_json->Result->EncryptedResponse->key->k;
			$method = $this->response_json->Result->EncryptedResponse->enc;
			$content = Crypto::decrypt_gzip($content, $key, $method, ImportRequest::FRAGMENT_MAX_BYTE_SIZE + 1);

			if (strlen($content) > ImportRequest::FRAGMENT_MAX_BYTE_SIZE) {
				throw new \ArloTraining\SnapshotProcessException('Snapshot file decryption exceeds maximum allowed size');
			}

			if (empty($content)) {
				throw new \ArloTraining\SnapshotProcessException('Snapshot file decryption produced an empty result');
			}

			$id = $this->importing_parts->add_import_part($this->import_part, $this->import_iteration, $content, $this->import_id);
			if (!empty($id)) {
				$this->is_finished = true;
			}
			unset($content);
		} else {
			throw new \Exception('The URI couldn\'t be empty');
		}
	}

	protected function get_remote_data($url) {
		$url = $this->validate_snapshot_url($url);

		$settings = get_option('arlo_settings', []);
		$ssl_verify = true;

		if (!empty($settings['disable_ssl_verification']) && $settings['disable_ssl_verification'] == 1) {
				$ssl_verify = false;
		}

		$current_url = $url;
		$redirects = 0;

		while (true) {
			$current_url = $this->validate_snapshot_url($current_url);
			$this->validate_remote_host($current_url);

			$args = [
					'timeout'             => self::TIMEOUT,
					'redirection'         => 0,
					'httpversion'         => '1.1',
					'sslverify'           => $ssl_verify,
					'reject_unsafe_urls'  => true,
					'limit_response_size' => self::MAX_RESPONSE_BYTES + 1,
					'headers'             => [
							'Referer' => $current_url,
					],
					'compress'            => true,
			];

			$parsed_host = wp_parse_url($current_url, PHP_URL_HOST);
			$hostname = (!empty($parsed_host)) ? $parsed_host : $current_url;
			$response = wp_remote_get($current_url, $args);

			// Handle errors
			if (is_wp_error($response)) {
					throw new \ArloTraining\PlatformAccessHttpException($hostname . ': ' . $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}

			$code = (int) wp_remote_retrieve_response_code($response);

			if ($this->is_redirect_response($code)) {
				if ($redirects >= self::MAX_REDIRECTS) {
					throw new \ArloTraining\PlatformAccessHttpException($hostname . ': Too many redirects'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}

				$current_url = $this->get_redirect_url($response, $current_url, $hostname);
				$redirects++;
				continue;
			}

			// Handle HTTP status codes
			switch ($code) {
					case 200:
							$body = wp_remote_retrieve_body($response);
							$this->validate_response_size($response, $body);

							if (empty($body)) {
									throw new \ArloTraining\PlatformAccessHttpException('Snapshot file download returned an empty response body');
							}
							return $body;

					default:
							throw new \ArloTraining\PlatformAccessHttpException($hostname . ' returned ' . $code . ' ' . wp_remote_retrieve_response_message($response)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		}
	}

	private function validate_snapshot_url($url) {
		if (!is_scalar($url)) {
			throw new \ArloTraining\SnapshotProcessException('Invalid snapshot URI');
		}

		$url = (string) $url;
		$scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
		if (!in_array($scheme, ['http', 'https'], true)) {
			throw new \ArloTraining\SnapshotProcessException('Invalid snapshot URI scheme: ' . esc_html($scheme));
		}

		return $url;
	}

	private function validate_remote_host($url) {
		$host = $this->get_url_host($url);
		if (!$this->is_allowed_snapshot_host($host)) {
			throw new \ArloTraining\SnapshotProcessException('Snapshot URI host is not allowed');
		}

		$addresses = $this->resolve_host_addresses($host);

		foreach ($addresses as $address) {
			if (!$this->is_public_address($address)) {
				throw new \ArloTraining\SnapshotProcessException('Snapshot URI host resolves to a disallowed network address');
			}
		}
	}

	private function get_url_host($url) {
		$host = wp_parse_url($url, PHP_URL_HOST);
		if (empty($host) || !is_string($host)) {
			throw new \ArloTraining\SnapshotProcessException('Invalid snapshot URI host');
		}

		return $this->normalize_hostname($host);
	}

	private function is_allowed_snapshot_host($host) {
		$platform_hostname = $this->get_platform_hostname();

		return $host === $platform_hostname || $this->host_matches_suffix($host, self::SNAPSHOT_API_DOMAIN);
	}

	private function get_platform_hostname() {
		if (!is_object($this->response_json) || !is_object($this->response_json->ArloMetadata ?? null) || empty($this->response_json->ArloMetadata->PlatformHostname) || !is_scalar($this->response_json->ArloMetadata->PlatformHostname)) {
			throw new \ArloTraining\SnapshotProcessException('Snapshot platform hostname is missing from import response');
		}

		$platform_hostname = $this->normalize_hostname((string) $this->response_json->ArloMetadata->PlatformHostname);
		$platform_domain = preg_quote(ltrim(Transport::PLATFORM_DOMAIN, '.'), '/');
		if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?\.' . $platform_domain . '$/i', $platform_hostname)) {
			throw new \ArloTraining\SnapshotProcessException('Snapshot platform hostname is invalid');
		}

		return $platform_hostname;
	}

	private function normalize_hostname($host) {
		return rtrim(strtolower(trim(trim($host), '[]')), '.');
	}

	private function host_matches_suffix($host, $suffix) {
		return $host !== $suffix && substr($host, -strlen('.' . $suffix)) === '.' . $suffix;
	}

	protected function resolve_host_addresses($host) {
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return [$host];
		}

		$addresses = [];
		$ipv4_addresses = gethostbynamel($host);
		if (is_array($ipv4_addresses)) {
			$addresses = array_merge($addresses, $ipv4_addresses);
		}

		if (function_exists('dns_get_record')) {
			$records = dns_get_record($host, DNS_A + DNS_AAAA);
			if (is_array($records)) {
				foreach ($records as $record) {
					if (!empty($record['ip'])) {
						$addresses[] = $record['ip'];
					}

					if (!empty($record['ipv6'])) {
						$addresses[] = $record['ipv6'];
					}
				}
			}
		}

		$addresses = array_unique($addresses);
		if (empty($addresses)) {
			throw new \ArloTraining\PlatformAccessHttpException($host . ': Could not resolve snapshot URI host'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return $addresses;
	}

	private function is_redirect_response($code) {
		return in_array($code, [301, 302, 303, 307, 308], true);
	}

	private function get_redirect_url($response, $url, $hostname) {
		$location = wp_remote_retrieve_header($response, 'location');
		if (is_array($location)) {
			$location = end($location);
		}

		if (!is_scalar($location) || trim((string) $location) === '') {
			throw new \ArloTraining\PlatformAccessHttpException($hostname . ' returned redirect without Location header'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$location = trim((string) $location);
		if (wp_parse_url($location, PHP_URL_SCHEME)) {
			return $location;
		}

		if (class_exists('WP_Http')) {
			return \WP_Http::make_absolute_url($location, $url);
		}

		throw new \ArloTraining\PlatformAccessHttpException($hostname . ' returned relative redirect that could not be resolved'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
	}

	private function is_public_address($address) {
		return (bool) filter_var(
			$address,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}

	private function validate_response_size($response, $body) {
		$content_length = wp_remote_retrieve_header($response, 'content-length');
		if (is_array($content_length)) {
			$content_length = end($content_length);
		}

		if (is_numeric($content_length) && (int) $content_length > self::MAX_RESPONSE_BYTES) {
			throw new \ArloTraining\SnapshotProcessException('Snapshot file download exceeds maximum allowed size');
		}

		if (strlen($body) > self::MAX_RESPONSE_BYTES) {
			throw new \ArloTraining\SnapshotProcessException('Snapshot file download exceeds maximum allowed size');
		}
	}
}
