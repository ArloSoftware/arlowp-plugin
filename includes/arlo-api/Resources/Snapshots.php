<?php

namespace ArloTraining\API\Resources;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
require_once 'Resource.php';

use ArloTraining\API\Resources\Resource;

/**
 * Resource client for requesting platform import snapshots.
 */
class Snapshots extends Resource {

	const SNAPSHOT_REQUEST_TIMEOUT_SECONDS = 20;

	/** @var string */
	protected $apiPath = '/resources/views/4ca89ebdc4e54490b6f4f46c347d0d9c/snapshots/'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Inherited Resource endpoint property contract.

	/**
	 * Requests an asynchronous import snapshot from the platform.
	 *
	 * @param mixed $post_data Snapshot request payload.
	 * @return mixed Platform snapshot request response.
	 * @throws \ArloTraining\PlatformAccessHttpException When the platform request fails.
	 */
	public function request_import( $post_data ) {
		$transport         = $this->__get( 'transport' );
		$original_api_path = $this->__get( 'api_path' );
		$original_timeout  = $transport->getRequestTimeout();
		$transport->setRequestTimeout( self::SNAPSHOT_REQUEST_TIMEOUT_SECONDS );

		try {
			$this->__set( 'api_path', $this->apiPath . 'requests/' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Inherited Resource endpoint property contract.

			return $this->request( null, $post_data );
		} finally {
			$this->__set( 'api_path', $original_api_path );
			$transport->setRequestTimeout( $original_timeout );
		}
	}
}
