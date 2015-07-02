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
	 * $state->values[ $property_name ] whenever $object->property_name is accessed.
	 *
	 * This architecture is so special so a method named the same as the JSON property can be used to validate, sanitize
	 * and/or transform the property value without having to reserve any method names.
	 *
	 * @package JSON_Loader
	 *
	 * @property Object $__parent__
	 * @property Property[] $__meta__
	 *
	 */
	class Object extends Base {

		/**
		 * @param object|array $value
		 * @param bool|Object $parent
		 * @param array $args
		 */
		function __construct( $value, $parent = false, $args = array() ) {

			if ( ! Util::has_root() ) {

			 	Util::set_root( $this );

			}

			$state = Loader::parse_class_header( $this, $parent );

			$value = Loader::set_object_defaults( $state->schema, $value );

			foreach ( $value as $property_name => $property_value ) {

				if ( ! isset( $state->schema[ $property_name ] ) ) {

					$state->extra_args[ $property_name ] = $property_value;

				} else {

					$property = $state->schema[ $property_name ];

					/**
					 * @todo Handle when the list is not the default type...
					 */

					$type = $property->default_type;

					if ( $type->class_name && 'object' === $type->array_of && 'array' === $type->base_type ) {

						$class_name = $type->class_name;

						$elements = $states = array();

						if ( ! empty( $value[ $property_name ] ) && is_array( $value[ $property_name ] ) ) {

							foreach ( $value[ $property_name ] as $element_value ) {

								$element = new $class_name( $element_value, $this );

								$states[] = Util::get_state( $element );

								$elements[] = $element;

							}
						}

						$state->values[ $property_name ] = $elements;

					} else {

						$state->values[ $property_name ] = Loader::instantiate_value(
							$this,
							$state->schema[ $property_name ],
							$state->namespace,
							$property_value
						);

					}
				}

			}

			Util::set_state( $this, $state );

			parent::__construct( $args );

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed|null
		 */
		function __get( $property_name ) {

			Util::push_class( $this );

			$value = null;

			$state = Util::get_state( $this );

			if ( 'parent' === $property_name ) {

				// @todo get rid of these
				$value = $state->parent;

			} else if ( '__parent__' == $property_name ) {

				$value = $state->parent;

			} else if ( '__meta__' == $property_name ) {

				$value = $state->schema;


			} else if ( Util::can_call( $callable = array( $this, $property_name ) ) ) {

				if ( ! array_key_exists( $property_name, $state->cached ) ) {

					$state->cached[ $property_name ] = call_user_func( $callable, $state->values[ $property_name ] );

				}
				$value = $state->cached[ $property_name ];

			} else if ( array_key_exists( $property_name, $state->schema ) && array_key_exists( $property_name, $state->values ) ) {

				$value = $state->values[ $property_name ];

			} else if ( array_key_exists( $property_name, $state->extra_args ) ) {

				$value = $state->extra_args[ $property_name ];

			} else if ( $state->parent instanceof Object ) {

				/**
				 * Bubble up...
				 */
				$value = $state->parent->__get( $property_name );

			} else {

				$class_name = implode( ', ', Util::class_stack() );
				if ( empty( $class_name ) ) {
					$class_name = get_class( $this );
				}
				Util::log_error( "There is no property \"{$property_name}\" in any of these class(es): {$class_name}." );

			}

			Util::pop_class();

			return $value;

		}


	}


}
