<?php

namespace JSON_Loader {

	/**
	 * Class State
	 *
	 * Contains the state for an object as well as it's schema.
	 *
	 */
	class State extends Base {

		/**
		 * @var string Namespace to try
		 */
		var $namespace = '\\';

		/**
		 * @var object Schema that drives instantiation
		 */
		var $schema;

		/**
		 * @var array Boolean value to determine if a property has had its value's method called and cached.
		 */
		var $cached = array();

		/**
		 * @var string The filepath of the JSON file, if loaded from a file
		 */
		var $filepath;

		/**
		 * @var Logger
		 */
		var $logger;

		/**
		 * @var Object
		 */
		var $owner;

		/**
		 * @var Object
		 */
		var $object_parent = null;

		private $_values = array();

		/**
		 * @param array|object|string $owner
		 * @param array|object|string $parent
		 * @param array $args {
		 *      @type string $namespace
		 * }
		 */
		function __construct( $owner, $parent = null, $args = array() ) {

			$this->owner = $owner;

			if ( ( $root = Util::root() ) !== $owner ) {

				$this->object_parent = $parent ? $parent : Util::root();

			}

			$this->schema = new \stdClass();

			parent::__construct( $args );

		}

		/**
		 * @param string $property_name
		 */
		function clear_cached_property( $property_name ) {

			unset( $this->cached[ $property_name ] );

		}

		/**
		 * @return string
		 */
		function object_hash() {

			return spl_object_hash( $this->owner );

		}

		/**
		 * @param string $property_name
		 * @param mixed $value
		 */
		function set_value( $property_name, $value ) {

			$this->_values[ $property_name ] = $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed
		 */
		function get_value( $property_name ) {

			do {

				$value = isset( $this->_values[ $property_name ] ) ? $this->_values[ $property_name ] : null;

				if ( ! is_null( $value ) ) {
					break;
				}

				/*
				 * If null and there is a callable for this property, get and cache it via __get()
				 */
				if ( is_object( $this->owner ) && Util::can_call( $callable = array( $this->owner, $property_name ) ) ) {

					$this->cached[ $property_name ] = $value = call_user_func( $callable, null );

					if ( ! is_null( $value ) ) {
						break;
					}

				}


				if ( ! isset( $this->object_parent ) ) {
					break;
				}

				if ( ! $this->object_parent instanceof \JSON_Loader\Object ) {
					break;
				}

				$state = Util::get_state( $this->object_parent );

				if ( ! $state->has_value( $property_name ) ) {
					break;
				}

				$value = $state->get_value( $property_name );

			} while ( false );

			return $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return bool
		 */
		function has_value( $property_name ) {

			return array_key_exists( $property_name, $this->_values );

		}

		/**
		 * @return array
		 */
		function values() {

			return $this->_values;

		}

		/**
		 * @param array $values
		 */
		function set_values( $values ) {

			$this->_values = $values;

		}

		/**
		 *
		 */
		function __clone() {
			$this->_values = array();
			$this->cached = array();
		}

	}


}
