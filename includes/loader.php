<?php

namespace JSON_Loader {

	class Loader {

		/**
		 * @var string The filepath of the JSON file, if loaded from a file
		 */
		static $filepath;

		/**
		 * @var Logger
		 */
		static $logger;

		/**
		 * @var Object[]
		 */
		static $schemas = array();

		/**
		 * @var string[]
		 */
		static $namespaces = array();

		/**
		 * @var mixed[]|Property[]
		 */
		static $properties = array();

		/**
		 * @var array
		 */
		private static $object_state = array();

		/**
		 * @param string $root_class
		 * @param string $filepath
		 * @param bool|Logger $logger
		 *
		 * @return Object
		 */
		static function load( $root_class, $filepath, $logger = false ) {

			self::$logger = $logger ? $logger : new Logger();

			if ( ! class_exists( $root_class ) ) {

				self::log_error( "The class {$root_class} is not a valid PHP class." );

			}
			$json_string = static::load_file( $filepath );

			$stdclass_object = static::load_json( $json_string, $filepath );

			$object = new $root_class( $stdclass_object );

			return $object;

		}

		/**
		 * @param string $json
		 * @param string $filepath
		 *
		 * @return object
		 */
		private function load_json( $json, $filepath = false ) {

			$data = (array) @json_decode( $json );

			if ( 0 == count( $data ) ) {

				if ( $filepath ) {

					self::log_error( "The {$name} file {$filepath} has invalid syntax." );

				} else if ( empty( $json ) ) {

					self::log_error( "The JSON value provided is empty." );

				} else {

					self::log_error( "The JSON value provided has invalid syntax." );

				}

			}

			return $data;

		}

		/**
		 * @param string $filepath
		 *
		 * @return string
		 */
		static function load_file( $filepath ) {

			self::$filepath = $filepath;

			if ( empty( $filepath ) ) {

				self::log_error( "The filename passed was empty." );

			}

			$json = file_get_contents( $filepath );

			return $json;

		}

		/**
		 * @param string $message
		 */
		static function log_error( $message ) {

			self::$logger->error( $message );

		}

		/**
		 * @param Object $object
		 * @param Object $parent
		 *
		 * @return State
		 */
		static function parse_class_header( $object, $parent ) {

			$class_name = get_class( $object );

			if ( ! isset( self::$schemas[ $class_name ] ) ) {

				$state = new State( $parent );

				$class_reflector = new \ReflectionClass( $class_name );

				$state->namespace = $class_reflector->getNamespaceName();

				self::$namespaces[ $class_name ] = $state->namespace;

				$lines = explode( "\n", $class_reflector->getDocComment() );

				for ( $index = 0; count( $lines ) > $index; $index ++ ) {

					$line = $lines[ $index ];

					if ( preg_match( '#^\s+\*\s*@property\s+([^ ]+)\s+\$([^ ]+)\s*(.*?)\s*((\{)(.*?)(\}?))?\s*$#', $line, $match ) ) {

						list( $line, $type, $property_name ) = $match;

						$args = array( 'description' => $match[ 3 ] );

						if ( isset( $match[ 6 ] ) ) {

							$subproperties = $match[ 6 ];

							if ( isset( $match[ 7 ] ) && '}' !== $match[ 7 ] ) {

								while ( false === strpos( $subproperties, '}' ) && count( $lines ) > ++ $index ) {

									$subproperties .= preg_replace( '#^\s*\*\s*(@.*)#', '$1', $lines[ $index ] );

								}

								$subproperties = preg_replace( '#^(.*)\}#', '$1', $subproperties );

							}

							$args = array_merge( $args, self::parse_sub_properties( $subproperties, $type ) );

						}

						$args['parent'] = $object;

						$property = new Property( $property_name, $type, $state->namespace, $args );

						$state->schema[ $property_name ] = $property;

					}

				}

				self::$schemas[ $class_name ] = $state;

			}

			return self::$schemas[ $class_name ];

		}


		/**
		 * @param string $sub_properties
		 * @param string $property_type
		 *
		 * @return array;
		 */
		static function parse_sub_properties( $sub_properties, $property_type ) {

			$args = array();

			foreach ( explode( '@', trim( $sub_properties ) ) as $sub_property ) {

				if ( empty( $sub_property ) ) {

					continue;

				}

				/**
				 * Convert each region of whitespace to just one space
				 */
				$sub_property = preg_replace( '#\s+#', ' ', $sub_property );

				/**
				 * Split each subproperty into words
				 */
				$words = explode( ' ', trim( $sub_property ) );

				$sub_property = $words[0];

				array_shift( $words );

				$arguments = implode( $words );

				if ( empty( $arguments ) ) {

					$arguments = true;

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

				$args[ $sub_property ] = $arguments;

			}

			return $args;

		}

		/**
		 * @param string $class_name
		 *
		 * @return string
		 */
		static function get_namespace( $class_name ) {

			return ! empty( self::$namespaces[ $class_name ] ) ? self::$namespaces[ $class_name ] : '';

		}


		/**
		 * @param Property[] $schema
		 * @param array[] $data
		 *
		 * @return mixed[]
		 */
		static function set_object_defaults( $schema, $data ) {

			if ( $was_array = is_array( $data ) ) {

				$data = (object) $data;

			}

			foreach ( $schema as $property_name => $attributes ) {

				if ( ! isset( $data->$property_name ) || is_null( $data->$property_name ) ) {

					$data->$property_name = ! is_null( $attributes->default ) ? $attributes->default : null;

				}

			}

			return $was_array ? (array) $data : $data;

		}

		/**
		 * @param Object $object
		 * @param int|Property $property
		 * @param string $namespace
		 * @param mixed $value
		 *
		 * @return mixed
		 */
		static function instantiate_value( $object, $property, $namespace, $value ) {

			$current_type = $property->get_current_type( $value );

			switch ( $base_type = $current_type->base_type ) {

				case 'string':

					// Do nothing
					break;

				case 'int':

					$value = intval( $value );
					break;

				case 'bool':
				case 'boolean':

					$value = (bool) $value;
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

							$value[ $index ] = self::instantiate_value(
								$object,
								new Property( $index, $element_type, $namespace, array(
									'parent' => $object,
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
		 * @param State $state
		 */
		static function set_state( $object, $state ) {

			self::$object_state[ spl_object_hash( $object ) ] = $state;

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
		 * @return State $state
		 */
		static function has_state_property( $object, $property_name ) {

			return property_exists( self::get_state( $object ), $property_name );

		}
		/**
		 * @param Object $object
		 * @param string $property_name
		 *
		 * @return State $state
		 */
		static function get_state_property( $object, $property_name ) {

			$properties = self::get_state_properties( $object );

			return isset( $properties[ $property_name ] )
				? $properties[ $property_name ]
				: null;

		}

		/**
		 * @param Object $object
		 *
		 * @return Object[]|mixed[]
		 */
		static function get_state_properties( $object ) {

			if ( ! $object instanceof Object ) {

				$properties = array();

			} else {

				$state = static::get_state( $object );

				$properties = $state->data;

			}

			return $properties;

		}

	}

}
