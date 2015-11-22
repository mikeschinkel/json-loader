<?php

namespace JsonLoader {

	/**
	 * Class Object
	 *
	 * This class has been architected very specifically to have no normally named properties and no normally named (non-magic) methods.
	 *
	 * It does this so it can represent a JSON object and use virtual properties to mirror the properties in the
	 * JSON object without having to require any property names to be reserved. Values are actually retrieved from
	 * $schema->get_value( $property_name ) whenever $object->property_name is accessed.
	 *
	 * This architecture is so special so a method named the same as the JSON property can be used to validate, sanitize
	 * and/or transform the property value without having to reserve any method names.
	 *
	 * @package JSON_Loader
	 *
	 * @method mixed _property_filter_( mixed $value, Property $property )
	 *
	 */
	class Object extends Base {

		/**
		 * @var Schema
		 */
		private $_schema_;

		/**
		 * @var Object
		 */
		private $_parent_;

		/**
		 * @var mixed[]
		 */
		private $_values_ = array();

		/**
		 * @var mixed[]
		 */
		private $_cached_ = array();

		/**
		 * @var bool
		 */
		private $_elements_instantiated_ = false;

		/**
		 * @var string
		 */
		private $_filepath_;

		/**
		 * @param object|array $value
		 * @param boolean|Object $parent
		 * @param array $args
		 */
		function __construct( $value, $parent = false, $args = array() ) {

			/**
			 * Load the schema in from the class headers
			 * including all parent headers.
			 */
			$this->_schema_ = $this->get_schema();

			/**
			 * If there is a parent Object, set it
			 */
			$this->_parent_ = $parent;

			/**
			 * Set the filepath, if one exists
			 */
			$this->_filepath_ = isset( $args['filepath'] ) ? $args['filepath'] : null;

			/**
			 * Initialize property values as defined in $this->schema
			 * and also call the defaults filter, if exists
			 */
			$this->do_initialize_values( $value );

			/*
			 * Assign all values in $args to Object properties, or if
			 * no matching properties add to $this->extra_args array.
			 */
			parent::__construct( $args );

			/**
			 * Any marked with @explode will be exploded from string to array
			 */
			$this->do_explode_properties();

		}

		/**
		 * Set the default values for the properties.
		 *
		 * @param array $values
		 */
		function do_initialize_values( $values ) {

			$values = (array) $values;

			foreach ( $this->get_properties() as $property_name => $property ) {

				if ( isset( $values[ $property_name ] ) ) {

					$this->set_value( $property_name, $values[ $property_name ] );

				}

				$value = $this->get_value( $property_name );

				if ( is_null( $value ) && ! is_null( $property->default ) ) {

					/*
					 * We have a @default attribute but not property value yet.
					 */

					if ( preg_match( '#^(!?)\s*\$(\w+)$#', $property->default, $match ) ) {

						/*
						 * Check to see if it is a back reference to another property.
						 * Defaults that begin with $ are back references, i.e.
						 *
						 *      @default $public
						 *
						 * The ! means to reverse the boolean value of the property.
						 */
						$back_reference = $match[2];

						$value = isset( $values[ $back_reference ] ) ? $values[ $back_reference ] : null;

						if ( '!' === $match[1] ) {

							$value = ! $value;
						}

					} else {

						/*
						 * Not a back reference so just a hardcoded value.
						 */
						$value = $property->default;

					}

					$this->set_value( $property_name, $value );

				}

				if ( ! $this->has_value( $property_name ) ) {

					$this->set_value( $property_name, null );
					$value = null;

				}

				/*
				 * Expand any arrays and instantiate any objects that need to be instantiated.
				 */
				$this->do_instantiate_value( $property_name, $value );
//				if ( $value !== $expanded_value ) {
//
//					$this->set_value( $property_name, $expanded_value );
//
//				}

				/*
				 * Call this filter if it exists.
				 * Was added to support @missing for WPLib CLI
				 * @see http://github.com/wplib/wplib-cli
				 */
				if ( method_exists( $this, '_property_filter_' ) ) {

					$filtered_value = $this->_property_filter_( $value, $property );
					if ( $value !== $filtered_value ) {

						$this->set_value( $property_name, $filtered_value );

					}

				}

			}

			return;

		}

		/**
		 * @param integer|string $property_name
		 * @param mixed $value
		 * @param Object|boolean $parent
		 *
		 * @return mixed
		 */
		function do_instantiate_value( $property_name, $value, $parent = false ) {

			$property = $this->get_property( $property_name );

			if ( $property->loadable && is_string( $value ) ) {
				// TODO Load here
			}

			$data_type = $property->get_data_type( $value );

			if ( $data_type ) {

				switch ( $base_type = $data_type->base_type ) {

					case 'string':
					case 'integer':
					case 'boolean':

						// Do nothing
						break;

					case 'array':
					case 'object':
					default:

						if ( is_null( $value ) ) {
							/**
							 * First parameter to class instantiation represents the properties
							 * and values to instatiate the object with thus it can be an array
							 * or an object, but it CANNOT be null. So default to array with no
							 * properties and values if null.
							 */
							$value = array();
						}

						if ( $data_type->class_name ) {

							$class_name = $data_type->class_name;

							if ( 'object' !== $data_type->array_of ) {

								$value = new $class_name( (array) $value, $this );

							} else {

								if ( ! empty( $value ) && is_array( $value ) ) {

									$elements = array();

									foreach ( $value as $element_value ) {

										$elements[] = new $class_name( $element_value, $this );

									}

									$value = $elements;

								}

							}

						}

						$this->set_elements_instantiated( $property_name, true );

						$this->set_value( $property_name, $value );

				}

			}

			return $value;

		}

		/**
		 * Return true if the array elements are objects and have been instantiated
		 *
		 * @param string $property_name
		 *
		 * @return boolean
		 */
		function get_elements_instantiated( $property_name ) {

			if ( isset( $this->_elements_instantiated_[ $property_name ] ) ) {

				$is_instantiated = $this->_elements_instantiated_[ $property_name ];

			} else {
		         /*
		          * If not already instantiated then if not an array
		          * we don't need to instantiate so return true.
		          * If an array we don't know if we need to instantiate
		          * so return false. Worst case is it will calculate
		          * multiple times before caching.
		          */
				$property = $this->get_property( $property_name );

				if ( $is_instantiated = ! is_null( $property ) ) {
					/*
					 * If the property_name was actually a method name from
					 * the generator then the property will be null.
					 */

					$data_types = implode( '~', array_keys( $property->data_types ) );

					/**
					 * Match 'array' or 'WhateverType[]`
					 */
					$is_instantiated = ! preg_match( '#~(array|.+?\[\])~#', "~{$data_types}~" );
				}
			}

			return $is_instantiated;

		}

		/**
		 * Set a flag once array elements have been instantiated.
		 * Set it to true even if not an array so we can cache the value.
		 *
		 * @param string $property_name
		 * @param mixed $value
		 *
		 * @return boolean
		 */
		function set_elements_instantiated( $property_name, $value ) {

			$this->_elements_instantiated_[ $property_name ] = $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return boolean
		 */
		function has_property( $property_name ) {

			return $this->_schema_->has_property( $property_name );

		}

		/**
		 * @param string $property_name
		 *
		 * @return Property
		 */
		function get_property( $property_name ) {

			return $this->_schema_->get_property( $property_name );

		}

		/**
		 * @param string $property_name
		 * @param Property $property
		 */
		function set_property( $property_name, $property ) {

			$this->_schema_->set_property( $property_name, $property );

		}

		/**
		 * @return Property[]
		 */
		function get_properties() {

			return $this->_schema_->properties();
		}

		/**
		 * @return array
		 */
		function get_loadable_properties() {

			$loadable = array();

			foreach( $this->get_properties() as $property_name => $property ) {

				if ( $property->loadable ) {

					$loadable[ $property_name ] = $property;

				}

			}

			return $loadable;

		}

		/**
		 * @param string $property_name
		 *
		 * @return boolean
		 */
		function has_method( $property_name ) {

			return method_exists( $this, $property_name ) && is_callable( array( $this, $property_name ) );

		}

		/**
		 * @param string $property_name
		 * @param mixed $value
		 *
		 * @return boolean
		 */
		function call_method( $property_name, $value = null ) {

			if ( 1 === count( func_get_args() ) ) {

				$value = call_user_func( array( $this, $property_name ) );

			} else {

				$value = call_user_func( array( $this, $property_name ), $value );

			}

			return $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return boolean
		 */
		function has_value( $property_name ) {

			return isset( $this->_values_[ $property_name ] );

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed
		 */
		function get_value( $property_name ) {

			$value = null;

			if ( $cached = $this->has_cached( $property_name ) ) {

				$value = $this->get_cached( $property_name );

			} else if ( isset( $this->_values_[ $property_name ] ) ) {

				$value = $this->_values_[ $property_name ];

			}

			if ( ! $cached && $this->has_method( $property_name ) ) {

				$value = $this->call_method( $property_name, $value );

				if ( $this->get_elements_instantiated( $property_name ) ) {

					/*
					 * Do not cache an array that has not yet had
					 * its elements instantiated
					 */
					$this->set_cached( $property_name, $value );

				}

			}

			return $value;

		}

		/**
		 * @param string $property_name
		 * @param mixed $property_value
		 *
		 * @return mixed
		 */
		function set_value( $property_name, $property_value ) {

			$this->_values_[ $property_name ] = $property_value;

		}

		/**
		 * @param string $method_name
		 *
		 * @return callable|false
		 */
		function get_callable( $method_name ) {

			$callable = method_exists( $this, $method_name ) && is_callable( array( $this, $method_name ) );

			return $callable ? $callable : false;

		}

		/**
		 * @return string
		 */
		function get_unique_id() {

			if ( ! ( $unique_id_field = Util::get_constant( 'ID_FIELD', $this ) ) ) {

				if ( ! ( $unique_id_field = Util::get_constant( 'SLUG', $this ) ) ) {

					Util::get_constant( 'ID_FIELD', $this, $throw_error = true );

				}

			}

			return $unique_id_field ? Util::dashify( $this->$unique_id_field ) : null;

		}

		/**
		 * Converts anything that has an @explode property from string to array.
		 */
		function do_explode_properties() {

			foreach ( $this->get_properties() as $property_name => $property ) {

				if ( $property->explode ) {

					if ( is_string( $value = $this->get_value( $property_name ) ) ) {

						$this->set_value(
							$property_name,
							explode( $property->explode, $value )
						);

					}

				}

			}

		}

		/**
		 * Parse the class header and return the schema
		 *
		 * @return Schema
		 */
		function get_schema() {

			static $schemas = array();

			if ( ! isset( $schemas[ $class_name = get_class( $this ) ] ) ) {

				$class_names = array( $class_name );

				do {
					if ( $parent_class = get_parent_class( end( $class_names ) ) ) {
						$class_names[] = $parent_class;
					}
				} while ( $parent_class );

				$schema = new Schema();

				$lines = '';

				$namespaces = array();

				/*
				 * Grab all headers from the current and all ancestor classes,
				 * reorder them from original Base class down to current class
				 * then output their namespace followed by their header, i.e.:
				 *
				 *      @namespace Base
				 *      /**
				 *       * @param string $name
				 *       *
				 *      @namespace Foo
				 *      /**
				 *       * @param string $description
				 *       *
				 */
				foreach ( array_reverse( $class_names ) as $class_name ) {

					$reflector = new \ReflectionClass( $class_name );

					$lines .= "@namespace {$reflector->getNamespaceName()}\n{$reflector->getDocComment()}\n";

				}
				/*
				 * Convert the namespaced headers to an array of lines
				 */
				$lines = explode( "\n", $lines );

				for ( $index = 0; count( $lines ) > $index; $index ++ ) {

					$line = $lines[ $index ];

					if ( preg_match( '#^\s*@namespace\s+(\w+)\s*$#', $line, $match ) ) {

						/*
						 * Since @namespaces come before headers, grab it and continue
						 */
						list( $line, $namespace ) = $match;
						continue;

					} elseif ( preg_match( '#^\s+\*\s*@property\s+([^ ]+)\s+\$([^ ]+)\s*(.*?)\s*(\{(.*?)(\}?))?\s*$#', $line, $match ) ) {

						/*
						 * Now grab the properties for the above @namespace,
						 * one line at a time.
						 */
						list( $line, $data_type, $property_name ) = $match;

						/*
						 * Everything after the property name and before an
						 * opening brace is the description.
						 */

						$args = array(
							'description' => $match[3],
							'namespace' => $namespace,
						);

						if ( isset( $match[5] ) ) {

							/*
							 * The rest of the line is an attribute for the property.
							 */
							$attributes = $match[5];

							if ( isset( $match[6] ) && '}' !== $match[6] ) {

								/*
								 * However if the line does not end with a closing brace ('}')
								 * then there are multiple attributes, one per line.
								 */
								while ( false === strpos( $attributes, '}' ) && count( $lines ) > ++ $index ) {

									/*
									 * So go and grab those, until we find a closing brace.
									 */
									$attributes .= preg_replace( '#^\s*\*\s*(@.*)#', '$1', $lines[ $index ] );

								}

								/*
								 * Finally, grab the last bit, through to the closing brace.
								 */
								$attributes = preg_replace( '#^(.*)\}#', '$1', $attributes );

							}

							/**
							 * Now parse the attributes and merge into $args
							 */

							$args = array_merge( $args, $this->do_parse_attributes( $attributes, $data_type ) );

						}

						/*
						 * Now make a new property instance given the collecting information.
						 */
						$property = Property::make_new(
							$property_name,
							Util::explode_data_types( $data_type, $namespace ),
							$args
						);

						/*
						 * Assign the new property to the Schema.
						 */
						$schema->set_property( $property_name, $property );

					}

				}
				/**
				 * Finally, same the Schema for reuse.
				 */
				$schemas[ $class_name ] = $schema;

			}

			/*
			 * Return the Schema
			 */

			return $schemas[ $class_name ];

		}

		/**
		 * @param string $attributes
		 * @param string $property_type
		 *
		 * @return array;
		 */
		function do_parse_attributes( $attributes, $property_type ) {

			$args = array();

			foreach ( explode( '@', trim( $attributes ) ) as $attribute ) {

				if ( empty( $attribute ) ) {

					continue;

				}

				/**
				 * Convert each region of whitespace to just one space
				 */
				$attribute = preg_replace( '#\s+#', ' ', $attribute );

				/**
				 * Split each subproperty into words
				 */
				$words = explode( ' ', trim( $attribute ) );

				$attribute = $words[0];

				array_shift( $words );

				$arguments = implode( $words );

				if ( empty( $arguments ) ) {

					$arguments = true;

				} else if ( 'null' === $arguments ) {

					$arguments = null;

				} else if ( preg_match( '#^(true|false)$#', $arguments ) ) {

					/**
					 * Test default for boolean true or false
					 * but only if the default (1st) type is 'bool'
					 */
					list( $default_type ) = explode( '|', $property_type );

					if ( 'bool' === $default_type ) {

						$arguments = 'true' === $arguments;

					}

				}

				$args[ $attribute ] = $arguments;

			}

			return $args;

		}

		/**
		 * @param string $property_name
		 * @param integer $level
		 *
		 * @return boolean
		 */
		function do_validate_value( $property_name, $level = 0 ) {

			$valid = true;

			$property_value = $this->get_value( $property_name );

			$property = $this->get_property( $property_name );

			if ( $property_value instanceof Object ) {

				$valid = Validator::instance()->validate_object( $property_value, $level );

			} else if ( $property->required && empty( $property_value ) ) {

				$error_msg = "Property \"{$property->property_name}\" is required for the \"%s\" %s";

				$error_msg = sprintf( $error_msg, $this->get_unique_id(), get_class( $this ) );

				if ( is_array( $property_value ) && 0 === count( $property_value ) ) {

					Util::log_error( "{$error_msg} to have array elements." );
					$valid = false;

				} else if ( is_null( $property_value ) ) {

					Util::log_error( "{$error_msg} to have array elements." );
					$valid = false;

				}

			}

			return $valid;

		}

		/**
		 * @param string $object_type
		 * @param string|boolean $message
		 *
		 * @return null|Object
		 */
		function get_ancestor( $object_type, $message = false ) {

			$object = $this;

			do {

				$object = $this->_parent_;

				if ( ( $found = $object->get_value( 'object_type' ) ) === $object_type ) {
					break;
				}

			} while ( ! is_null( $found ) );

			if ( ! $message || is_null( $object ) ) {

				$value = $object;

			} else {

				if ( $object->has_property( $message ) ) {

					$value = $object->get_value( $message );

				} else if ( $object->has_method( $message ) ) {

					$value = $object->call_method( $message );

				} else {

					$value = null;

				}

			}

			return $value;

		}

		function has_parent() {
			return $this->_parent_ instanceof Object;
		}

		function get_parent() {
			return $this->_parent_;
		}

		function set_parent( $parent ) {

			$this->_parent_ = $parent;

		}

		function has_filepath() {
			return ! is_null( $this->_filepath_ );
		}

		function get_filepath() {
			return $this->_filepath_;
		}

		function set_filepath( $filepath ) {

			$this->_filepath_ = $filepath;

		}

		function has_cached( $property_name ) {
			return isset( $this->_cached_[ $property_name ] );
		}

		function get_cached( $property_name ) {
			return isset( $this->_cached_[ $property_name ] )
				? $this->_cached_[ $property_name ]
				: null;
		}

		function set_cached( $property_name, $value ) {

			$this->_cached_[ $property_name ] = $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed|null
		 */
		function __get( $property_name ) {

			$value = $this->get_cached( $property_name );

			if ( is_null( $value ) ) {

				Util::push_class( $this );

				$value = null;

				$parent = $this;

				$had_property = false;

				do {

					if ( $parent->has_property( $property_name ) ) {

						$value = $parent->get_value( $property_name );

						$had_property = true;

					}
					if ( $parent->has_method( $property_name ) ) {

						$value = $parent->call_method( $property_name, $value );

						$had_property = true;

					}

					if ( ! is_null( $value ) ) {

						$this->set_cached( $property_name, $value );
						break;

					}

					$parent = $parent->get_parent();

				} while ( $parent instanceof Object );

				if ( ! $had_property ) {

					$class_name = implode( ', ', array_unique( Util::class_stack() ) );
					if ( empty( $class_name ) ) {
						$class_name = get_class( $this );
					}

					if ( false === strpos( $class_name, ',' ) ) {
						Util::log_error( "There is no property \"{$property_name}\" in the class {$class_name}." );
					} else {
						Util::log_error( "There is no property \"{$property_name}\" in any of these class(es): {$class_name}." );
					}
				}

				Util::pop_class();

			}
			return $value;

		}
	}

}
