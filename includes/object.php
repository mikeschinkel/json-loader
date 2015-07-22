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
	 * $state->get_value( $property_name ) whenever $object->property_name is accessed.
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

		private $_state_;

		/**
		 * @param object|array $value
		 * @param bool|Object $parent
		 * @param array $args
		 */
		function __construct( $value, $parent = false, $args = array() ) {

			if ( ! Util::has_root() ) {

			 	Util::set_root( $this );

			}

			$this->_state_ = $state = Loader::parse_class_header( $this, $parent );

			$value = Loader::set_object_defaults( $state->schema, $value );

			foreach ( $value as $property_name => $property_value ) {

				if ( ! isset( $state->schema->$property_name ) ) {

					$state->extra_args[ $property_name ] = $property_value;

				} else {

					$property = $state->schema->$property_name;

					/**
					 * @todo Handle when the list is not the default type...
					 */

					$type = $property->default_type;

					if ( $type->class_name && 'object' === $type->array_of && 'array' === $type->base_type ) {

						$class_name = $type->class_name;

						$elements = array();

						if ( ! empty( $value[ $property_name ] ) && is_array( $value[ $property_name ] ) ) {

							foreach ( $value[ $property_name ] as $element_value ) {

								$elements[] = new $class_name( $element_value, $this );

							}
						}

						$state->set_value( $property_name, $elements );

					} else {

						$state->set_value( $property_name, Util::instantiate_value(
							$this,
							$state->schema->$property_name,
							$state->namespace,
							$property_value
						) );

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
				$value = $state->object_parent;

			} else if ( '__parent__' == $property_name ) {

				$value = $state->object_parent;

			} else if ( '__meta__' == $property_name ) {

				$value = $state->schema;


			} else if ( Util::can_call( $callable = array( $this, $property_name ) ) ) {

				if ( ! array_key_exists( $property_name, $state->cached ) ) {

					$state->cached[ $property_name ] = call_user_func( $callable, $state->get_value( $property_name ) );

				}
				$value = $state->cached[ $property_name ];

			} else if ( property_exists( $state->schema, $property_name ) && $state->has_value( $property_name ) ) {

				$value = $state->get_value( $property_name );

			} else if ( array_key_exists( $property_name, $state->extra_args ) ) {

				/**
				 * @todo Is this ever used?  If yes, then it will fail for arrays because there will only be one for every array element.
				 *       Not sure how to fix yet.
				 */
				$value = $state->extra_args[ $property_name ];

			} else if ( $state->object_parent instanceof Object &&
			            ( Util::has_property( $state->object_parent, $property_name ) || Util::can_call( array( $state->object_parent, $property_name ) ) ) ) {

				/**
				 * Bubble up...
				 */
				$value = $state->object_parent->__get( $property_name );

			} else {

				$class_name = implode( ', ', array_unique( Util::class_stack() ) );
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


