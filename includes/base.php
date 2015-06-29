<?php

namespace JSON_Loader {

	class Base {

		/**
		 * @var array
		 */
		var $extra_args = array();

		/**
		 * @param array|string|object $args
		 */
		function __construct( $args = array() ) {

			$this->set_args( $args );

		}

		/**
		 * @param $args
		 */
		function set_args( $args ) {
			if ( is_string( $args ) ) {

				parse_str( $args, $args );

			}

			foreach ( $args as $name => $value ) {

				if ( 'extra_args' !== $name && property_exists( $this, $name ) ) {

					$this->{$name} = $value;

				} else {

					$this->extra_args[ $name ] = $value;

				}

			}

		}
	}

}
