<?php

/**
 * Namespace JSON_Loader
 */
namespace JSON_Loader {

	/**
	 * Class Property
	 *
	 * @package JSON_Loader
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
		 * @var bool|string
		 */
		var $namespace = false;

		/**
		 * @var bool
		 */
		var $required = false;

		/**
		 * @var bool
		 */
		var $default = null;

		/**
		 * @var Type[]
		 */
		var $types;

		/**
		 * @var Type
		 */
		var $default_type;

		/**
		 * @var Object
		 */
		var $parent;

		/**
		 * @param string $property_name
		 * @param string|string[]|Type|Type[] $types
		 * @param string $namespace
		 * @param array $args {
		 */
		function __construct( $property_name, $types, $namespace, $args = array() ) {

			$args['property_name'] = $property_name;

			$args['types'] = array();

			$type_args = array_intersect_key( $args, get_class_vars( 'JSON_Loader\Type' ) );

			foreach ( explode( '|', $types ) as $type ) {

				if ( ! $type instanceof Type ) {

					$type = new Type( $type, $namespace, $type_args );

				}

				$args['types'][ (string) $type ] = $type;

			}

			$args['namespace'] = $namespace;

			$args['default_type'] = 0 < count( $args['types'] ) ? reset( $args['types'] ) : false;

			/**
			 * Let parent assign $args to properties
			 */
			parent::__construct( $args );

		}


		/**
		 * @param $value
		 *
		 * @return Type
		 */
		function get_current_type( $value ) {

			if ( is_null( $value ) ) {

				$current_type = $this->default_type;

			} else {

				$value_type = new Type( gettype( $value ), $this->namespace );

				$is_array = 'array' === $value_type->base_type;

				foreach ( $this->types as $type ) {

					if ( ! $is_array && $type->is_equal( $value_type ) ) {

						$current_type = $type;
						break;

					} else if ( $is_array && $type->is_equal( $value_type, $value ) ) {

						$current_type = $type;
						break;

					}

				}

				if ( ! isset( $current_type ) ) {

					$message = sprintf(
						"Failed to load %s using %s value: %s.\n" .
						"\nCorrrect the PHPDoc for %s or change the data in your JSON file.",
						(string)$type,
						(string)$value_type,
						$this->_as_string( $value ),
						$this->identifier()
					);

					Loader::log_error( $message );

				}

			}

			return $current_type;

		}

		/**
		 * Provide an identifer for this property to be output in error messages.
		 *
		 * @return string
		 */
		function identifier() {

			return get_class( $this->parent ) . "->{$this->property_name}";

		}

		/**
		 * Convert any value type to string for outputting in an error message
		 *
		 * @param mixed $value
		 *
		 * @return string
		 */
		private function _as_string( $value ) {

			ob_start();
			print_r( $value );
			return ob_get_clean();

		}

	}

}
