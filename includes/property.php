<?php

/**
 * Namespace JsonLoader
 */
namespace JsonLoader {

	/**
	 * Class Property
	 *
	 * @package JsonLoader
	 *
	 * @property mixed $value
	 */
	class Property extends Base {

		/**
		 * @var string
		 */
		var $property_name;

		/**
		 * @var string
		 */
		var $description;

		/**
		 * @var boolean|string
		 */
		var $namespace = false;

		/**
		 * @var bool
		 */
		var $required = false;

		/**
		 * @var string|boolean
		 */
		var $default = null;

		/**
		 * @var string|boolean
		 */
		var $missing = null;

		/**
		 * If @loadable then the value can either be a JSON object to map to a PHP object, or the path where a /wplib.json can be found.
		 * @var string|boolean
		 */
		var $loadable = null;

		/**
		 * If @dashified then value will be transformed with Util::underscorify()
		 *
		 * Means to replace spaces and dashes with underscores.
		 *
		 * @var bool
		 */
		var $underscorified = false;

		/**
		 * If @dashified then value will be transformed with Util::dashify()
		 *
		 * Means to replace spaces and underscores with dashes.
		 *
		 * @var bool
		 */
		var $dashified = false;

		/**
		 * If @lowercased then value will be transformed with strtolower()
		 *
		 * @var bool
		 */
		var $lowercased = false;

		/**
		 * If @uppercased then value will be transformed with strtoupper()
		 *
		 * @var bool
		 */
		var $uppercased = false;

		/**
		 * If @propercased then value will be transformed with ucfirst()
		 *
		 * @var bool
		 */
		var $propercased = false;

		/**
		 * @var bool
		 */
		var $initializer = null;

		/**
		 * Character to explode() on for string to array
		 *
		 * @var string
		 */
		var $explode = false;

		/**
		 * @var Data_Type[]
		 */
		var $data_types;

		/**
		 * @var Data_Type
		 */
		var $default_type;

		/**
		 * @param string $property_name
		 * @param string|string[]|Data_Type|Data_Type[] $data_types
		 * @param array $args
		 *
		 * @return static
		 */
		static function make_new( $property_name, $data_types, $args = array() ) {

			// @todo Reuse properties that match the property to be created.

			return new static( $property_name, $data_types, $args );

		}
		/**
		 * @param string $property_name
		 * @param string|string[]|Data_Type|Data_Type[] $data_types
		 * @param array $args
		 */
		function __construct( $property_name, $data_types, $args = array() ) {

			$args['property_name'] = $property_name;

			$args['data_types'] = array();

			$type_args = array_intersect_key( $args, get_class_vars( 'JsonLoader\Data_Type' ) );

			if ( ! is_array( $data_types ) ) {

				$data_types = explode( '|', $data_types );

			}

			foreach ( $data_types as $index => $data_type ) {

				$type_args['namespace'] = Util::parse_namespace( $data_type );

				if ( 0 === $index ) {
					$args['namespace'] = $type_args['namespace'];
				}

				$args['data_types'][ (string) $data_type ]
					= Data_Type::make_new( $data_type, $type_args );

			}


			$args['default_type'] = 0 < count( $args['data_types'] ) ? reset( $args['data_types'] ) : null;

			if ( ! empty( $args['explode'] ) ) {

				$args['explode'] = preg_replace( '#^([\'"](.*)[\'"]|(.*))$#', '$2$3', trim( $args['explode'] ) );

			}

			/**
			 * Let object_parent assign $args to properties
			 */
			parent::__construct( $args );

		}

		function has_data_type( $data_type ) {

			return isset( $this->data_types[ $data_type ] );

		}

		/**
		 * @param $value
		 *
		 * @return Data_Type
		 */
		function get_data_type( $value ) {

			if ( is_null( $value ) ) {

				$result = $this->default_type;

			} else {

				$value_type = new Data_Type( gettype( $value ), array(
					'namespace' => $this->namespace,
				));

				$is_array = 'array' === $value_type->base_type;

				foreach ( $this->data_types as $data_type ) {

					if ( ! $is_array && $data_type->is_equal( $value_type ) ) {

						$result = $data_type;
						break;

					} else if ( $is_array && $data_type->is_equal( $value_type, $value ) ) {

						$result = $data_type;
						break;

					} else {

						$result = null;

					}

				}

			}

			return $result;

		}

		/**
		 * @param string $extra_arg
		 *
		 * @return boolean
		 */
		function is_true( $extra_arg ) {

			return (bool) ( array_key_exists( $extra_arg, $this->extra_args ) && $this->extra_args[ $extra_arg ] );

		}

		/**
		 * @param string $extra_arg
		 *
		 * @return boolean
		 */
		function is_false( $extra_arg ) {

			return (bool) ( array_key_exists( $extra_arg, $this->extra_args ) && ! $this->extra_args[ $extra_arg ] );

		}

		/**
		 * @param string $element_name
		 *
		 * @return boolean
		 */
		function has_extra( $element_name ) {

			return isset( $this->extra_args[ $element_name ] );

		}

		/**
		 * @param string $element_name
		 *
		 * @return mixed
		 */
		function get_extra( $element_name ) {

			$extra = isset( $this->extra_args[ $element_name ] )
				? $this->extra_args[ $element_name ]
				: null;

			return $this->cast( $extra );
		}

		/**
		 * Convert strings 'true', 'false' and 'null' to true, false and null.
		 *
		 * Only if property declared to support boolean or null, respectively.
		 *
		 * @param string $value
		 *
		 * @return mixed
		 *
		 * @todo Move this to loading of the data?
		 */
		function cast( $value ) {

			$regex = '#^(true|false|null)$#';

			if ( is_string( $value ) && preg_match( $regex, strtolower( $value ), $match ) ) {

				switch ( $match[1] ) {

					case 'true':
					case 'false':

						if ( $this->has_data_type( 'boolean' ) ) {

							$value = 'true' === $value;

						}
						break;

					case 'null':

						if ( $this->has_data_type( 'null' ) ) {

							$value = null;

						}
						break;

				}

			}

			return $value;

		}

	}

}
