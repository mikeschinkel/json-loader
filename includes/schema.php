<?php

namespace JsonLoader {

	/**
	 * Class Schema
	 *
	 * Contains the list of properties for an object
	 *
	 */
	class Schema extends Base {

		/**
		 * @var string Namespace to try
		 */
		var $namespace = '\\';

		/**
		 * @var Property[] Schema that drives instantiation
		 */
		var $properties;

		/**
		 * @var string The filepath of the JSON file, if loaded from a file
		 */
		var $filepath;

		/**
		 * @var Object
		 */
		var $owner;

		/**
		 * @param array $args {
		 *
		 * @internal param array|object|string $owner
		 * @internal param array|object|string $parent
		 * @internal param string $namespace }* }
		 */
		function __construct( $args = array() ) {

			$this->properties = array();

			parent::__construct( $args );

		}

		/**
		 * @param string $property_name
		 *
		 * @return boolean
		 */
		function has_property( $property_name ) {

			$properties = $this->properties;

			return isset( $properties[ $property_name] );

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed
		 */
		function get_property( $property_name ) {

			return isset( $this->properties[ $property_name] )
				? $this->properties[ $property_name]
				: null;

		}

		/**
		 * @param string $property_name
		 * @param Property $property
		 */
		function set_property( $property_name, $property ) {

			$this->properties[ $property_name] = $property;

		}

		/**
		 * @return Property[]
		 */
		function properties() {

			return $this->properties;
		}

	}


}
