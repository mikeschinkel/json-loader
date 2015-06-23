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

		if ( ! class_exists( $found = $class_name ) ) {

			$found = null;

			\JSON_Loader\Loader::log_error( "Class {$passed_class} does not exist." );

		}

		return $found;

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


