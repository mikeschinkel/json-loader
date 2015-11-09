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
		static $object_schema = array();

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
		 * Return the class namespace for an object or a class that can be loaded.
		 *
		 * @param object|string $object
		 *
		 * @return string
		 */
		static function get_namespace( $object ) {

			$class_name = is_object( $object ) ? get_class( $object ) : $object;

			try {
				$reflector = new \ReflectionClass( $class_name );
				$namespace = $reflector->getNamespaceName();
			} catch (\Exception $e ) {
				$namespace = null;
			}

			return $namespace;

		}

		/**
		 * Return the local class for an object or a class that can be loaded.
		 *
		 * @param object|string $object
		 *
		 * @return string
		 */
		static function get_local_class( $object ) {

			$class_name = is_object( $object )
				? get_class( $object )
				: $object;

			try {
				$reflector = new \ReflectionClass( $class_name );
				if ( $reflector->inNamespace() ) {
					$base_class = $reflector->getShortName();
				} else {
					$base_class = $class_name;
				}
			} catch (\Exception $e ) {
				$base_class = $class_name;
			}

			return $base_class;

		}

		/**
		 * @param string $class_name
		 * @param boolean|string $namespace
		 *
		 * @return string
		 */
		static function get_qualified_class_name( $class_name, $namespace = false ) {

			if ( $class_name instanceof Object && ! $namespace ) {

				$namespace  = self::get_namespace( $class_name );
				$class_name = get_class( $class_name );

			}

			$local_class = self::get_local_class( $class_name );

			$class_name = "\\{$namespace}\\{$local_class}";

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
		 * @param boolean|null $lowercase
		 *
		 * @return string
		 */
		static function underscorify( $string_with_dashes, $lowercase = true ) {

			$string = str_replace( array( '-', ' ' ), '_', $string_with_dashes );

			if ( $lowercase ) {
				$string = strtolower( $string );
			}

			return $string;

		}

		/**
		 * Replace underscores and spaces in input string with dashes.
		 *
		 * @param string $string_with_underscores
		 * @param boolean|null $lowercase
		 *
		 * @return string
		 */
		static function dashify( $string_with_underscores, $lowercase = true ) {

			$string = str_replace( array( '_', ' ' ), '-', $string_with_underscores );
			$string = strtolower( $string );

			return $string;

		}

		/**
		 * @param string $identifier
		 * @param string $prefix
		 *
		 * @return string
		 */
		static function get_prefixed_identifier( $identifier, $prefix ) {

			/**
			 * Convert all identifiers to using lowercase and underscores
			 */
			$identifier = Util::underscorify( strtolower( $identifier ) );

			$regex = '#^' . preg_quote( "{$prefix}_" ) . '#';

			if ( ! preg_match( $regex, $identifier ) ) {
				/**
				 * If the post type was not prefixed with short prefix, prefix it.
				 */
				$identifier = "{$prefix}_{$identifier}";

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
		 * @param string $constant_name
		 * @param boolean|string $class_name
		 * @param boolean $validate
		 *
		 *
		 * @return mixed|null
		 */
		static function get_constant( $constant_name, $class_name = false, $validate = false ) {

			if ( ! $class_name ) {

				$class_name = get_called_class();

			}

			if ( is_object( $class_name ) ) {

				$class_name = get_class( $class_name );

			}

			$value = defined( $constant_ref = "{$class_name}::{$constant_name}" )
				? constant( $constant_ref )
				: null;

			if ( ! $value && $validate ) {

				$message = "No %s constant set in class %s.";

				Util::log_error( sprintf( $message, $constant_name, $class_name ) );

			}

			return $value;

		}

		/**
		 * @param string $message
		 */
		static function log_error( $message ) {

			Loader::instance()->logger->error( $message );

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
		 * @param string|array $dir
		 */
		static function mkdir( $dir = array() ) {

			$dirs = ! is_array( $dir ) ? array( $dir ) : $dir;

			foreach ( $dirs as $dir ) {

				if ( ! is_dir( $dir ) ) {

					mkdir( $dir, 0777, true );

				}

			}

		}

		/**
		 * Take a type string such as 'string', '\\Foo', 'Foo\\Bar' or '\\Foo\\Bar', and returning the namespace.
		 *
		 * @example Namespaces returned:
		 *
		 *      'string'        => null
		 *      '\\Foo'         => null
		 *      'Foo\\Bar'      => Foo
		 *      '\\Foo\\Bar'    => Foo
		 *
		 * @note the backslash escaping of '\\Foo\\Bar' etc.
		 *
		 * @param string $type
		 *
		 * @return string|null
		 */
		static function parse_namespace( $type ) {

			list( $namespace ) = false !== strpos( $type, '\\' )
				? explode( '\\', ltrim( $type, '\\' ) )
				: array( null );

			return $namespace;

		}

		/**
		 * Take a type string such as 'string', '\\Foo', 'Foo\\Bar' or '\\Foo\\Bar', and returning the local class name.
		 *
		 * @example Namespaces returned:
		 *
		 *      'string'        => null
		 *      'object'        => object
		 *      'Foo'           => Foo
		 *      '\\Foo'         => Foo
		 *      'Foo\\Bar'      => Bar
		 *      '\\Foo\\Bar'    => Bar
		 *
		 * @note the backslash escaping of '\\Foo\\Bar' etc.
		 *
		 * @param string $type
		 *
		 * @return string|null
		 */
		static function parse_local_class( $type ) {

			if ( self::is_builtin_type( $type ) ) {
				/*
				 * $type === 'string', 'boolean', 'integer', etc.
				 */
				$local_class = null;

			} else if ( false !== strpos( $type, '\\' ) ) {
				/*
				 * $type === '\\Foo\\Bar' or 'Foo\\Bar',
				 */
				$local_class = substr( $type, strrpos( $type, '\\' ) + 1 );

			} else {

				/*
				 * $type === 'object' or 'Foo'
				 */
				$local_class = $type;

			}

			return $local_class;

		}

		/**
		 * Returns true if $type contains a string that can be returned by gettype().
		 *
		 * @note This does not mean that the type HAS been declared,
		 * only that its NOT one of the type returned by gettype().
		 *
		 * @param string $type
		 * @param array $omit_types
		 *
		 * @return int
		 */
		static function is_builtin_type( $type, $omit_types = array() ) {

			if ( ! is_array( $omit_types ) ) {

				$omit_types = explode( '|', $omit_types );

			}

			$builtin_types = implode( '|', array_diff(
				explode( '|', 'boolean|integer|double|string|object|array|resource|NULL|unknown type' ),
				$omit_types
			));

			return preg_match( "#^({$builtin_types})$#", $type );

		}

		/**
		 * @param string $data_type
		 * @param string $namespace
		 *
		 * @return array
		 */
		static function explode_data_types( $data_type, $namespace ) {

			$data_types = array_map( function ( $data_type ) use ( $namespace ) {
				if ( false === strpos( $data_type, '\\' ) && ! Util::is_builtin_type( $data_type ) ) {

					$data_type = "\\{$namespace}\\{$data_type}";

				}

				return $data_type;
			}, explode( '|', $data_type ) );

			return $data_types;

		}

	}

}
