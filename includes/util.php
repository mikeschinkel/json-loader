<?php

namespace JSON_Loader {

	use JSON_Loader;

	/**
	 * Class Util
	 *
	 * Utility methods.
	 *
	 */
	class Util {

		/**
		 * @var array
		 */
		static $_class_stack = array();

		/**
		 * @var array
		 */
		static $object_state = array();

		/**
		 * @var Object
		 */
		static $root;

		/**
		 * @var array
		 */
		private static $_schemas = array();


		/**
		 * @param array $args
		 * @param array|string $defaults
		 *
		 * @return array
		 */
		static function parse_args( $args, $defaults ) {

			if ( is_string( $defaults ) ) {

				parse_str( $args, $args );

			}

			return array_merge( $defaults, $args );

		}

		/**
		 * @param Object $root
		 * @return bool
		 */
		static function has_root() {

			return isset( self::$root );

		}

		/**
		 * @return Object
		 */
		static function root() {

			return self::$root;

		}

		/**
		 * @param Object $root
		 */
		static function set_root( $root ) {

			self::$root = $root;

		}

		/**
		 * @param object|string $class_name
		 */
		static function push_class( $class_name ) {

			if ( ! is_object( $class_name ) ) {

				self::$_class_stack[] = $class_name;

			} else {

				self::$_class_stack[] = get_class( $class_name );

			}

		}

		/**
		 * @return object
		 */
		static function pop_class() {

			return count( self::$_class_stack ) ? array_pop( self::$_class_stack ) : null;

		}

		/**
		 * @return array
		 */
		static function class_stack() {

			return self::$_class_stack;

		}

		/**
		 * @param object|string $default_class
		 *
		 * @return null|object
		 */
		static function top_class( $default_class = null ) {

			if ( is_object( $default_class ) ) {

				$default_class = get_class( $default_class );

			}

			return count( self::$_class_stack ) ? reset( self::$_class_stack ) : $default_class;

		}

		/**
		 * @param object|string $object
		 *
		 * @return string
		 */
		static function get_namespace( $object ) {

			$class_name = is_object( $object ) ? get_class( $object ) : $object;

			$reflector = new \ReflectionClass( $class_name );

			return $reflector->getNamespaceName();

		}

		/**
		 * @param object|string $object
		 *
		 * @return string
		 */
		static function get_baseclass( $object ) {

			$class_name = is_object( $object ) ? get_class( $object ) : $object;

			if ( false !== strpos( $class_name, '\\' ) ) {

				$reflector = new \ReflectionClass( $class_name );

				$class_name = $reflector->inNamespace() ? $reflector->getShortName() : $class_name;

			}

			return $class_name;

		}

		/**
		 * @param string $class_name
		 * @param bool|string $namespace
		 *
		 * @return string
		 */
		static function get_qualified_class_name( $class_name, $namespace = false ) {

			if ( $class_name instanceof Object && ! $namespace ) {

				$namespace  = self::get_namespace( $class_name );
				$class_name = get_class( $class_name );

			}

			$class_name = self::get_baseclass( $class_name );

			$class_name = "\\{$namespace}\\{$class_name}";

			if ( ! class_exists( $class_name ) ) {

				self::log_error( "Class {$class_name} does not exist." );

			}

			return $class_name;

		}

		/**
		 * Returns the type where 'object' is returned as the specific object type.
		 *
		 * @param mixed $value
		 *
		 * @return string
		 */
		static function get_type( $value ) {

			return is_object( $value ) ? get_class( $value ) : gettype( $value );

		}

		/**
		 * Get the SLUG constant for an object or a class name.
		 *
		 * @param object|string $object
		 *
		 * @return string
		 */
		static function get_class_slug( $object ) {

			$class_name = is_object( $object ) ? get_class( $object ) : $object;

			return constant( "{$class_name}::SLUG" );

		}

		/**
		 * Replace dashes and spaces in input string with underscores.
		 *
		 * @param string $string_with_dashes
		 *
		 * @return string
		 */
		static function underscorify( $string_with_dashes ) {

			return str_replace( array( '-', ' ' ), '_', $string_with_dashes );

		}

		/**
		 * Replace underscores and spaces in input string with dashes.
		 *
		 * @param string $string_with_underscores
		 *
		 * @return string
		 */
		static function dashify( $string_with_underscores ) {

			return str_replace( array( '_', ' ' ), '-', $string_with_underscores );

		}

		/**
		 * @param array $callable
		 *
		 * @return bool
		 */
		static function can_call( $callable ) {

			if ( is_string( $callable ) ) {

				$can_call = function_exists( $callable ) && is_callable( $callable );

			} else if ( is_array( $callable ) ) {

				list( $object, $method ) = $callable;

				do {

					$can_call = true;

					if ( method_exists( $object, $method ) && is_callable( $callable ) ) {

						break;

					}

					if ( property_exists( $object, 'object' ) && self::can_call( $callable = array( $object->object, $method ) ) ) {

						break;

					}

					$can_call = false;

				} while ( false );

			}

			return $can_call;
		}

		/**
		 * @param Object $object
		 * @param State $state
		 */
		static function set_state( $object, $state ) {

			self::$object_state[ spl_object_hash( $object ) ] = $state;

		}

		/**
		 * @param Object $object
		 * @param string $property_name
		 *
		 * @return State $state
		 */
		static function get_state_value( $object, $property_name ) {

			$properties = self::get_state_values( $object );

			return isset( $properties[ $property_name ] )
				? $properties[ $property_name ]
				: null;

		}

		/**
		 * @param string $identifier
		 * @param string $short_prefix
		 *
		 * @return string
		 */
		static function get_prefixed_identifier( $identifier, $short_prefix ) {

			/**
			 * Convert all identifiers to using lowercase and underscores
			 */
			$identifier = Util::underscorify( strtolower( $identifier ) );

			$regex = '#^' . preg_quote( "{$short_prefix}_" ) . '#';

			if ( ! preg_match( $regex, $identifier ) ) {
				/**
				 * If the post type was not prefixed with short prefix, prefix it.
				 */
				$identifier = "{$short_prefix}_{$identifier}";

			}

			return $identifier;

		}

		/**
		 * Strip a prefix from a string seperated by an $seperator (typically dash or underscore).
		 *
		 * @example
		 *
		 *      self::strip_prefix( 'aa_foobar', 'aa' ) => 'foobar'
		 *      self::strip_prefix( 'aa_foobar', 'aa', '_' ) => 'foobar'
		 *
		 * @param string $identifier
		 * @param string $prefix
		 * @param string $seperators
		 *
		 * @return string
		 */
		static function strip_prefix( $identifier, $prefix, $seperators = '-_' ) {

			/**
			 * Convert all identifiers to using lowercase and underscores
			 */
			$identifier = Util::underscorify( strtolower( $identifier ) );

			$regex = '#^' . preg_quote( $prefix ) . "[{$seperators}](.*)$#";

			if ( preg_match( $regex, $identifier, $match ) ) {
				/**
				 * If the post type was prefixed with short prefix, strip it.
				 */
				$identifier = $match[1];

			}

			return $identifier;

		}

		/**
		 * @param string|string[] $value
		 *
		 * @return array
		 */
		static function comma_string_to_array( $value ) {

			return is_string( $value )
				? array_map( 'trim', explode( ',', $value ) )
				: $value;

		}

		/**
		 * @param Object $object
		 *
		 * @return State $state
		 */
		static function get_state( $object ) {

			$hash = spl_object_hash( $object );

			return isset( self::$object_state[ $hash ] )
				? self::$object_state[ $hash ]
				: null;

		}

		/**
		 * @param Object $object
		 * @param string $property_name
		 *
		 * @return bool
		 */
		static function has_parent_property( $object, $property_name ) {

			/**
			 * @var State $state
			 */
			$state = Util::get_state( $object );

			return $state->object_parent && self::has_property( $state->object_parent, $property_name );

		}

		/**
		 * @param Object|array $object
		 * @param string $property_name
		 *
		 * @return bool
		 */
		static function has_property( $object, $property_name = null ) {

			if ( is_array( $object ) && 2 <= count( $object ) ) {

				list( $object, $property_name ) = $object;

			}

			if ( ! is_a( $state = Util::get_state( $object ), '\JSON_Loader\State' ) ) {

				$has_property = property_exists( $object, $property_name );

			} else {

				$has_property = preg_match( '#^(__parent__|__meta__)$#', $property_name ) ||
				                property_exists( $state->schema, $property_name ) ||
				                array_key_exists( $property_name, $state->extra_args ) ||
				                property_exists( $object, $property_name );
			}

			return $has_property;
		}

		/**
		 * @param string $constant_name
		 * @param bool|string $class_name
		 *
		 * @return mixed|null
		 */
		static function get_constant( $constant_name, $class_name = false ) {

			if ( ! $class_name ) {

				$class_name = get_called_class();

			}

			if ( is_object( $class_name ) ) {

				$class_name = get_class( $class_name );

			}

			return defined( $constant_ref = "{$class_name}::{$constant_name}" )
				? constant( $constant_ref )
				: null;

		}

		/**
		 * @param string $message
		 */
		static function log_error( $message ) {

			Loader::$logger->error( $message );

		}

		/**
		 * Filters values out that match the core default for the underlying platform.
		 *
		 * @param Property[] $args         List of property arguments to filter
		 * @param string $default_arg_name Attribute specific to the app_object, e.g. "@wp_default" for WPLib CLI.
		 *
		 * @return array
		 *
		 * @example
		 *
		 *      No value provided @default=true @wp_default=false => include in returned $args
		 *      No value provided @default=false @wp_default=false => DO NOT include in returned $args
		 *      true provided @default=true @wp_default=false => include in returned $args
		 *      true provided @default=true @wp_default=true => DO NOT include in returned $args
		 *      true provided @default=false @wp_default=true => DO NOT include in returned $args
		 *
		 */
		static function filter_default_values( $args, $default_arg_name ) {

			foreach ( $args as $property_name => $property ) {

				if ( ! $property instanceof Property ) {

					continue;

				}

				if ( is_null( $property_value = $property->value() ) ) {

					unset( $args[ $property_name ] );
					continue;

				}

				$default_arg_value = $property->get_extra( $default_arg_name );

				if ( ! preg_match( '#^(!?)\s*\$(\w+)$#', $default_arg_value, $matches ) ) {

					$default_property_value = $default_arg_value;

				} else {

					if ( ! isset( $args[ $default_property_name = $matches[2] ] ) ) {

						Util::log_error( "Property {$default_property_name} not declared but referenced in a @{$default_arg_name} for {$property_name} yet. Must be declared before referenced." );

					} else {

						$not = '!' === $matches[1];

						$default_property = $args[ $default_property_name ];

						$default_property_value = $not ? ! $default_property->value() : $default_property->value();

					}

				}

				if ( $default_property_value === $property_value ) {

					unset( $args[ $property_name ] );

				}

			}

			return $args;

		}

		/**
		 * @param Object $object
		 *
		 * @return array
		 */
		static function get_initializer_properties( $object ) {

			$initializers = array();

			$meta = $object->__meta__;

			foreach ( $meta as $property_name => $property ) {

				if ( $property->initializer ) {

					$initializers[ $property_name ] = $property;

				}

			}

			return $initializers;

		}

		/**
		 * Converts anything that has an @explode property from string to array.
		 *
		 * @param Object $object
		 * @param Property[] $args
		 *
		 * @return array
		 *
		 */
		static function explode_args( $object, $args ) {

			foreach ( $args as $property_name => $property ) {

				if ( ! $property instanceof Property ) {

					continue;

				}

				if ( $property->explode ) {

					if ( is_string( $value = $property->value() ) ) {

						$state = Util::get_state( $object );

						$state->clear_cached_property( $property_name );

						$value = explode( $property->explode, $value );

						$property->value = $value;

						$state->set_value( $property_name, $value );

						Util::set_state( $object, $state );

					}

				}

			}

			return $args;

		}

		/**
		 * @param string $filename
		 * @param string $class_name
		 *
		 * @return string
		 */
		static function get_template_filepath( $filename, $class_name ) {

			if ( is_dir( dirname( $filename ) ) && is_file( $filename ) ) {

				$filepath = $filename;

			} else {

				$filename = trim( $filename, '/' );



				$filepath = static::get_template_dir( $class_name ) . "/{$filename}";

			}

			return $filepath;

		}

		/**
		 * @param object|string $class
		 *
		 * @return string
		 */
		static function get_template_dir( $class ) {

			$reflector = new \ReflectionClass( is_object( $class ) ? get_class( $class ) : $class );

			return dirname( dirname( $reflector->getFileName() ) ) . '/templates';

		}


		/**
		 * @param Object $object
		 * @return string
		 */
		static function unique_id( $object ) {

			if ( ! ( $unique_id_field = Util::get_constant( 'ID_FIELD', $object ) ) ) {

				$unique_id_field = Util::get_constant( 'SLUG', $object );

			}

			$state = Util::get_state( $object );

			if ( ! $state->has_value( $unique_id_field ) ) {

				$message = "No ID_FIELD constant set in class %s. ID_FIELD identifies the field name contain a unique identifying value.";

				Util::log_error( sprintf( $message, get_class( $object ) ) );

			}

			return Util::dashify( $object->$unique_id_field );

		}

		/**
		 * @param Object $object
		 *
		 * @return bool
		 */
		static function has_object_schema( $object ) {

			return self::has_class_schema( $object ) &&
				array_key_exists( spl_object_hash( $object ), self::$_schemas[ get_class( $object ) ] );

		}

		/**
		 * @param Object|string $object
		 *
		 * @return bool
		 */
		static function has_class_schema( $object ) {

			$class_name = is_object ( $object ) ? get_class( $object ) : $object;

			return array_key_exists( $class_name, self::$_schemas );

		}


		/**
		 * @param Object|string $object
		 * @param Object $parent
		 *
		 * @return State
		 */
		static function clone_object_schema( $object, $parent ) {

			/**
			 * @var State $schema
			 */
			if ( self::has_class_schema( $object ) && count( self::$_schemas[ $class_name = get_class( $object ) ] ) ) {

				$schema = clone( reset( self::$_schemas[ $class_name ] ) );
				$schema->object_parent = $parent;

			} else {

				$schema = null;

			}

			return $schema;

		}

		/**
		 * @param Object|string $object
		 * @param State $schema
		 */
		static function set_object_schema( $object, $schema ) {

			$class_name = is_object ( $object ) ? get_class( $object ) : $object;

			if ( ! self::has_class_schema( $object ) ) {
				self::$_schemas[ $class_name ] = array();
			}

			self::$_schemas[ $class_name ][ spl_object_hash( $object ) ] = $schema;

		}

		/**
		 * @param Object|string $object
		 * @return State
		 */
		static function get_object_schema( $object ) {

			$class_name = is_object ( $object ) ? get_class( $object ) : $object;

			return self::has_class_schema( $object )
				? self::$_schemas[ $class_name ][ spl_object_hash( $object ) ]
				: null;

		}

		/**
		 * @param Object $object
		 * @param int|Property $property
		 * @param string $namespace
		 * @param mixed $value
		 *
		 * @return mixed
		 */
		public static function instantiate_value( $object, $property, $namespace, $value ) {

			$current_type = $property->get_current_type( $value );

			switch ( $base_type = $current_type->base_type ) {

				case 'string':
				case 'int':
				case 'bool':
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
					if ( $current_type->array_of ) {

						foreach ( (array) $value as $index => $element_value ) {

							$element_type = $current_type->element_type();

							$parent_object = is_subclass_of( $element_value, '\JSON_Loader\Object' )
								? $element_value
								: $object;

							$value[ $index ] = self::instantiate_value(
								$parent_object,
								new Property( $index, $element_type, $namespace, array(
									'parent_object' => $parent_object,
								) ),
								$namespace,
								$element_value
							);

						}
						break;

					} else if ( $current_type->class_name ) {

						$class_name = $current_type->class_name;

						$value = new $class_name( (array) $value, $object );
						break;

					}

			}

			return $value;

		}

		/**
		 * @param Object $object
		 * @param string $property_name
		 *
		 * @return State $state
		 */
		public static function has_state_value( $object, $property_name ) {

			return property_exists( self::get_state( $object ), $property_name );

		}

		/**
		 * @param Object $object
		 *
		 * @return Object[]|mixed[]
		 */
		public static function get_state_values( $object ) {

			if ( ! $object instanceof Object ) {

				$properties = array();

			} else {

				$state = self::get_state( $object );

				$properties = $state->values();

			}

			return $properties;

		}

	}

}
