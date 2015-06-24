<?php

/**
 * Loads JSON into defined classes to enable default values, data cleansing, validation, etc.
 *
 * @package   JsonLoader
 * @author    Mike Schinkel <mike@newclarity.net>
 * @copyright 2015 NewClarity Consulting LLC
 * @license   https://en.wikipedia.org/wiki/MIT_License
 * @version   0.1.0
 * @link      http://github.com/mikeschinkel/json-loader
 * @requires  PHP 5.3
 */

require( __DIR__ . '/includes/autoloader.php' );

class JSON_Loader {

	private static $_class_stack = array();

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
	 * @return string
	 */
	static function get_namespace( $object ) {

		$class_name = is_object( $object ) ? get_class( $object ) : $object;

		$reflector = new \ReflectionClass( $class_name );

		return $reflector->getNamespaceName();

	}

	/**
	 * @param object|string $object
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

		if ( $class_name instanceof \JSON_Loader\Object && ! $namespace ) {

			$namespace  = self::get_namespace( $class_name );
			$class_name = get_class( $class_name );

		}

		$class_name = self::get_baseclass( $class_name );

		$class_name = "\\{$namespace}\\{$class_name}";

		if ( ! class_exists( $class_name ) ) {

			\JSON_Loader\Loader::log_error( "Class {$class_name} does not exist." );

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

}


