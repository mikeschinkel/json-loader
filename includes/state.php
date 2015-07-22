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

			$this->_values[ $this->object_hash() ][ $property_name ] = $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed
		 */
		function get_value( $property_name ) {

			return $this->_values[ $this->object_hash() ][ $property_name ];

		}

		/**
		 * @param string $property_name
		 *
		 * @return bool
		 */
		function has_value( $property_name ) {

			return array_key_exists( $property_name, $this->_values[ $this->object_hash() ] );

		}

		/**
		 * @return array
		 */
		function values() {

			return $this->_values[ $this->object_hash() ];

		}

		/**
		 * @param array $values
		 */
		function set_values( $values ) {

			$this->_values[ $this->object_hash() ] = $values;

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
