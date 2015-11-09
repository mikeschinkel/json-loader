<?php

namespace JSON_Loader;

/**
 * Autoloads classes from the /classes subdirectory.
 *
 * Class WPPM_Foo_Bar_Baz should be found in class-foo-bar-baz.php
 *
 * @param string $class_name
 *
 */
class Autoloader {

	static function autoload( $class_name ) {

		$class_name = preg_match( '#^(\\\\)?JSON_Loader\\\\(.*)$#', $class_name, $match ) ? $match[2] : $class_name;

		if ( is_file( $class_file = __DIR__ . strtolower( Util::dashify( "/{$class_name}.php" ) ) ) ) {

			require( $class_file );

		}

	}

}
spl_autoload_register( array( '\JSON_Loader\AutoLoader', 'autoload' ) );


