<?php

namespace JSON_Loader {

	class Validator extends Base {

		var $errors = array();

		/**
		 * @var self
		 */
	    static $instance;

		/**
		 * @return self
		 */
	    static function instance() {

			return self::$instance;

	    }

		/**
		 * @param mixed $value
		 * @param array $args {
		 *      @type integer $level
		 * }
		 *
		 * @return boolean
		 */
		function validate( $value, $args = array() ) {

			$args = Util::parse_args( $args, array(
				'level' => 0,
			));

			if ( 0 === $args['level'] ) {

				$this->errors = array();

			}


			if ( $value instanceof Object ) {

				$valid = $this->validate_object( $value, 1 + $args['level'] );

			} else if ( is_array( $value ) ) {

				foreach ( $value as $index => $element_value ) {

					$valid = true;

					if ( ! $this->validate( $element_value, 1 + $args['level'] ) ) {

						$valid = false;

					}

				}

			}

			if ( 0 < count( $this->errors ) && 0 === $args['level'] ) {

			    Util::log_error( "Validation Failed:\n\n\t- " . implode( "\n\t- ", $this->errors ) );

			}

			return $valid;

		}

		/**
		 * @param Object $object
		 * @param integer $level
		 *
		 * @return boolean
		 */
		function validate_object( $object, $level = 0 ) {

			$valid = true;

			foreach ( $object->get_properties() as $property_name => $property ) {

				if ( ! $object->do_validate_value( $property_name, $level ) ) {

					$valid = false;

				}

			}

			return $valid;

		}

	}

	Validator::$instance = new Validator();

}
