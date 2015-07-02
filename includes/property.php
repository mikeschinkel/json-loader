<?php

/**
 * Namespace JSON_Loader
 */
namespace JSON_Loader {

	/**
	 * Class Property
	 *
	 * @package JSON_Loader
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

			if ( ! empty( $args['explode'] ) ) {

				$args['explode'] = preg_replace( '#^([\'"](.*)[\'"]|(.*))$#', '$2$3', trim( $args['explode'] ) );

			}

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
						"\nCorrrect the PHPDoc for %s or change the values in your JSON file.",
						(string) $type,
						(string) $value_type,
						$this->_as_string( $value ),
						$this->identifier()
					);

					Util::log_error( $message );

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

		/**
		 * @param string $extra_arg
		 *
		 * @return bool
		 */
		function is_true( $extra_arg ) {

			return (bool) ( array_key_exists( $extra_arg, $this->extra_args ) && $this->extra_args[ $extra_arg ] );

		}

		/**
		 * @param string $extra_arg
		 *
		 * @return bool
		 */
		function is_false( $extra_arg ) {

			return (bool) ( array_key_exists( $extra_arg, $this->extra_args ) && ! $this->extra_args[ $extra_arg ] );

		}

		/**
		 * @param string $extra_arg
		 *
		 * @return mixed
		 */
		function get_extra( $extra_arg ) {

			$extra = array_key_exists( $extra_arg, $this->extra_args ) ? $this->extra_args[ $extra_arg ] : null;

			return is_string( $extra ) ? trim( $extra ) : $extra;

		}

		/**
		 * Invokes the logic in the Object to default, sanitize, standardize, etc.
		 *
		 * @return mixed
		 */
		function value() {

			$object = $this->parent;

			$property_name = $this->property_name;

			if ( Util::has_property( $object, $property_name ) ) {

				$value = $object->$property_name;

			} else if ( Util::has_parent_property( $object, $property_name ) ) {

				$value = $object->parent->$property_name;

			} else {

				$value = null;

				Util::log_error( sprintf(
					"Class %s does not have property in State->schema or ->values when attempting to get Property->value for property %s",
					get_class( $this->parent ),
					$property_name
				) );

			}

			return $value;

		}

		/**
		 * Return the 'State' object of this Property's parent Object.
		 *
		 * @return State
		 */
		function parent_state() {

			/**
			 * @var Object $object
			 */
			$object = $this->parent;

			return Util::get_state( $object );

		}

		/**
		 * Return the 'State' object of this Property's parent Object.
		 *
		 * @param State $state
		 */
		function set_parent_state( $state ) {

			/**
			 * @var Object $object
			 */
			$object = $this->parent;

			Util::set_state( $object, $state );

		}

		/**
		 * Special case to get 'Value'
		 *
		 * Get the $property_name element of the 'values' array from the State of this
		 * Property's parent Object if it exists or property is not 'value', then fail.
		 *
		 * @param string $property_name
		 * @return mixed
		 */
		function __get( $property_name ) {

			if ( 'value' === $property_name ) {

				$value = $this->value();

			} else {

				$value = null;

				Util::log_error( sprintf(
					"Class %s does not have property in State->schema or ->values when attempting to set Property->value for property %s",
					get_class( $this->parent ),
					$property_name
				));

			}

			return $value;

		}

		/**
		 * Special case to set 'Value'
		 *
		 * Set the $property_name element of the 'values' array in the State of this
		 * Property's parent Object if it exists, otherwise fail.
		 * If not 'value' just set the property.
		 *
		 * @param string $property_name
		 * @param $value
		 */
		function __set( $property_name, $value ) {

			if ( 'value' === $property_name ) {

				$state = $this->parent_state();

				if ( array_key_exists( $this->property_name, $state->values ) ) {

					$state->values[ $this->property_name ] = $value;

				} else {

					Util::log_error( sprintf(
						"Class %s does not have property in State->schema or ->values when attempting to set Property->value for property %s",
						get_class( $this->parent ),
						$property_name
					));

				}

				$this->set_parent_state( $state );

			}

			if ( true ) {

				$this->$property_name = $value;
			}

		}

	}

}
