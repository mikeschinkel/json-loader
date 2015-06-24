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
	 *
	 * @property Object $parent
	 *
	 */
	class Object extends Base {

		/**
		 * @param object|array $data
		 * @param bool|Object $parent
		 * @param array $args
		 */
		function __construct( $data, $parent = false, $args = array() ) {

			$state = Loader::parse_class_header( $this, $parent );

			$data = Loader::set_object_defaults( $state->schema, $data );

			foreach ( $data as $property_name => $property_value ) {

				if ( ! isset( $state->schema[ $property_name ] ) ) {

					$state->extra_args[ $property_name ] = $property_value;

				} else {

					$state->data[ $property_name ] = Loader::instantiate_value(
						$this,
						$state->schema[ $property_name ],
						$state->namespace,
						$property_value
					);

				}

			}

			Loader::set_state( $this, $state );

			parent::__construct( $args );

		}

		/**
		 * @param string $property
		 *
		 * @return mixed|null
		 */
		function __get( $property ) {

			\JSON_Loader::push_class( $this );

			$value = null;

			$state = Loader::get_state( $this );

			if ( 'parent' == $property ) {

				$value = $state->parent;

			} else if ( method_exists( $this, $property ) && is_callable( $callable = array( $this, $property ) ) ) {

				if ( ! array_key_exists( $property, $state->cached ) ) {

					$state->cached[ $property ] = call_user_func( $callable, $state->data[ $property ] );

				}
				$value = $state->cached[ $property ];

			} else if ( array_key_exists( $property, $state->schema ) && array_key_exists( $property, $state->data ) ) {

				$value = $state->data[ $property ];

			} else if ( array_key_exists( $property, $state->extra_args ) ) {

				$value = $state->extra_args[ $property ];

			} else if ( $state->parent instanceof Object ) {

				/**
				 * Bubble up...
				 */
				$value = $state->parent->__get( $property );

			} else {

				$class_name = implode( ', ', \JSON_Loader::class_stack() );
				if ( empty( $class_name ) ) {
					$class_name = get_class( $this );
				}
				Loader::log_error( "There is no property \"{$property}\" in any of these class(es): {$class_name}." );

			}

			\JSON_Loader::pop_class();

			return $value;

		}

	}

}
