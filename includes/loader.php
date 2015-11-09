<?php

namespace JSON_Loader {

	class Loader {

		/**
		 * @var string The filepath of the JSON file, if loaded from a file
		 */
		var $filepath;

		/**
		 * @var Logger
		 */
		var $logger;

		/**
		 * @var self
		 */
	    static $instance;

		/**
		 * @return Loader
		 */
	    static function instance() {

			return self::$instance;

	    }

		/**
		 * @param string $filepath
		 * @param callable $class_factory
		 * @param array $args {
		 *      @type boolean|Logger $logger
		 *      @type boolean|Object $parent
		 * }
		 * @return \JSON_Loader\Object
		 */
		function load( $filepath, $class_factory, $args = array() ) {

			$args = Util::parse_args( $args, array(
				'logger' => false,
				'parent' => false,
			));

			$this->logger = $args['logger'] ? $args['logger'] : new Logger();

			$this->filepath = $filepath;

			if ( empty( $filepath ) ) {
				Util::log_error( "The filename passed was empty." );
			}

			if ( ! is_file( $filepath ) ) {
				Util::log_error( "The file {$filepath} does not exist." );
			}

			$json = file_get_contents( $filepath );

			if ( empty( $json ) ) {
				Util::log_error( "The JSON value provided is empty." );
			}

			$data = @json_decode( $json );

			if ( is_null( $data ) ) {
				Util::log_error( "The JSON file {$filepath} has invalid syntax." );
			}

			if ( ! is_callable( $class_factory ) ) {
				Util::log_error( "Class factory is not a callable." );
			}

			$object = call_user_func( $class_factory, $data, $filepath, $args );

			return $object;

		}

	}

	Loader::$instance = new Loader();

}
