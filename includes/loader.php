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
		 * @var mixed[]|Property[]
		 */
		static $properties = array();


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

				Util::log_error( "The class {$root_class} is not a valid PHP class." );

			}
			$json_string = static::load_file( $filepath );

			$stdclass_object = static::load_json( $json_string, $filepath );

			$object = new $root_class( $stdclass_object );

			return $object;

		}

		/**
		 * @param string $json
		 * @param bool|string $filepath
		 *
		 * @return object
		 */
		private function load_json( $json, $filepath = false ) {

			$data = (array) @json_decode( $json );

			if ( 0 == count( $data ) ) {

				if ( $filepath ) {

					Util::log_error( "The JSON file {$filepath} has invalid syntax." );

				} else if ( empty( $json ) ) {

					Util::log_error( "The JSON value provided is empty." );

				} else {

					Util::log_error( "The JSON value provided has invalid syntax." );

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

				Util::log_error( "The filename passed was empty." );

			}

			$json = file_get_contents( $filepath );

			return $json;

		}

		/**
		 * @param Object $object
		 * @param Object $parent
		 *
		 * @return State
		 */
		static function parse_class_header( $object, $parent ) {

			if ( Util::has_class_schema( $object ) ) {

				$state = Util::clone_object_schema( $object, $parent );

			} else {

				$class_name = get_class( $object );

				$state = new State( $object, $parent );

				$class_reflector = new \ReflectionClass( $class_name );

				$state->namespace = $class_reflector->getNamespaceName();

				$lines = explode( "\n", $class_reflector->getDocComment() );

				for ( $index = 0; count( $lines ) > $index; $index ++ ) {

					$line = $lines[ $index ];

					if ( preg_match( '#^\s+\*\s*@property\s+([^ ]+)\s+\$([^ ]+)\s*(.*?)\s*((\{)(.*?)(\}?))?\s*$#', $line, $match ) ) {

						list( $line, $type, $property_name ) = $match;

						$args = array( 'description' => $match[3] );

						if ( isset( $match[6] ) ) {

							$subproperties = $match[6];

							if ( isset( $match[7] ) && '}' !== $match[7] ) {

								while ( false === strpos( $subproperties, '}' ) && count( $lines ) > ++ $index ) {

									$subproperties .= preg_replace( '#^\s*\*\s*(@.*)#', '$1', $lines[ $index ] );

								}

								$subproperties = preg_replace( '#^(.*)\}#', '$1', $subproperties );

							}

							$args = array_merge( $args, self::parse_sub_values( $subproperties, $type ) );

						}

						$args['parent_object'] = $object;

						$property = new Property( $property_name, $type, $state->namespace, $args );

						$state->schema->$property_name = $property;

					}

				}


			}

			Util::set_object_schema( $object, $state );

			return Util::get_object_schema( $object );

		}


		/**
		 * @param string $sub_properties
		 * @param string $property_type
		 *
		 * @return array;
		 */
		static function parse_sub_values( $sub_properties, $property_type ) {

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
		 * @param Property[] $schema
		 * @param array[] $data
		 *
		 * @return mixed[]
		 */
		static function set_object_defaults( $schema, $data ) {

			if ( $was_array = is_array( $data ) ) {

				$data = (object) $data;

			}

			foreach ( (array) $schema as $property_name => $attributes ) {

				if ( ! isset( $data->$property_name ) || is_null( $data->$property_name ) ) {

					if ( is_null( $attributes->default ) ) {

						$data->$property_name = null;

					} else if ( preg_match( '#^\$(\w+)$#', $attributes->default, $match ) ) {

						$data_property = $match[ 1 ];

						$data->$property_name = isset( $data->$data_property ) ? $data->$data_property : null;

					} else {

						$data->$property_name = $attributes->default;

					}


				}

			}

			return $was_array ? (array) $data : $data;

		}


	}

}
