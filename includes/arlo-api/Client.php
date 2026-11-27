<?php

namespace ArloTraining\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Client {

	/** Resource classes that may be requested through the client. */
	private const RESOURCES = array( 'Snapshots' );

	/** Location for overloaded data. */
	private $data = array();

	public function __construct( $platform_name, $transport = null, $plugin_version = '' ) {
		$this->__set( 'platform_name', $platform_name );
		$this->__set( 'plugin_version', $plugin_version );
		$this->__set( 'transport', $transport );
	}

	public function __set( $name, $value ) {
		$this->data[ $name ] = $value;
	}

	public function __get( $name ) {
		if ( array_key_exists( $name, $this->data ) ) {
			return $this->data[ $name ];
		}

		return null;
	}

	public function __call( $name, $arguments ) {
		if ( ! in_array( $name, self::RESOURCES, true ) ) {
			throw new \BadMethodCallException( 'Unsupported Arlo API resource.' );
		}

		if ( ! $this->__get( $name ) ) {
			require_once __DIR__ . '/Resources/' . $name . '.php';
			$class = "ArloTraining\\API\\Resources\\$name";
			$this->__set( $name, new $class( $this->__get( 'platform_name' ), $this->__get( 'transport' ), $this->__get( 'plugin_version' ) ) );
		}

		return $this->__get( $name );
	}
}
