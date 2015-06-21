<?php

namespace JSON_Loader {

	/**
	 * Class Object
	 *
	 * This class has been architected very specifically to have no properties and no regular (non-magic) methods.
	 *
	 * It stores and retrieves its state via Loader::set_state() and Loader::get_state().
	 *
	 * It does this so it can represent a JSON object and use virtual properties to mirror the properties in the
	 * JSON object without having to require any property names to be reserved. Values are actually retrieved from
	 * $state->data[ $property_name ] whenever $object->property_name is accessed.
	 *
	 * This architecture is so special so a method named the same as the JSON property can be used to validate, sanitize
	 * and/or transform the property value without having to reserve any method names.
	 *
	 * @package JSON_Loader
	 */
	class Object extends Base {

		/**
		 * @param object|array $data
		 * @param bool|Object $parent
		 * @param array $args
		 */
		function __construct( $data, $parent = false, $args = array() ) {

			$state = Loader::parse_class_header( (Object)$this, get_class( $this ) );

			$data = Loader::set_object_defaults( $state->schema, $data );

			foreach ( $data as $property_name => $property_value ) {

				if ( ! isset( $state->schema[ $property_name ] ) ) {

					$state->extra_args[ $property_name ] = $property_value;

				} else {

					$state->data[ $property_name ] = Loader::instantiate_value(
						(Object)$this,
						$state->schema[ $property_name ],
						$state->namespace,
						$property_value
					);

				}

			}

			Loader::set_state( (Object)$this, $state );

			parent::__construct( $args );

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed|null
		 */
		function __get( $property_name ) {

			$value = null;

			$state = Loader::get_state( (Object)$this );

			if ( isset( $state->schema[ $property_name ] ) && ! is_null( $state->data[ $property_name ] ) ) {

				$value = $state->data[ $property_name ];

			} else if ( method_exists( $state, $property_name ) ) {

				$value = $state->data[ $property_name ] = call_user_func( array( $state, $property_name ) );

			} else if ( isset( $state->extra_args[ $property_name ] ) ) {

				$value = $state->extra_args[ $property_name ];

			}

			return $value;

		}

	}

}
