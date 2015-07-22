<?php

namespace JSON_Loader {

	class Validator extends Base {

		static $errors = array();

		/**
		 * @param mixed $value
		 * @param int $level
		 *
		 * @return bool
		 */
		static function validate( $value, $level = 0 ) {

			if ( 0 === $level ) {

				self::$errors = array();

			}


			if ( $value instanceof Object ) {

				$valid = static::validate_object( $value, 1 + $level );

			} else if ( $value instanceof Property ) {

				$valid = static::validate_property( $value, 1 + $level );

			} else if ( is_array( $value ) ) {

				foreach ( $value as $index => $element_value ) {

					$valid = true;

					if ( ! static::validate( $element_value, 1 + $level ) ) {

						$valid = false;

					}

				}

			}

			if ( 0 < count( self::$errors ) && 0 === $level ) {

			    Util::log_error( "Validation Failed:\n\n\t- " . implode( "\n\t- ", self::$errors ) );

			}

			return $valid;

		}

		/**
		 * @param Object $object
		 * @param int $level
		 *
		 * @return bool
		 */
		static function validate_object( $object, $level = 0 ) {

			$state = Util::get_state( $object );

			$valid = true;

			foreach ( (array)$state->schema as $property_name => $property ) {

				$property_value = $state->get_value( $property_name );

				if ( ! is_array( $property_value ) ) {

					if ( ! static::validate_property( $property, $level ) ) {

						$valid = false;

					}

				} else {

					foreach ( $property_value as $index => $value ) {

						if ( ! static::validate( $value, $level ) ) {

							$valid = false;

						}

					}

				}

			}

			return $valid;

		}

		/**
		 * @param Property $property
		 * @param int $level
		 * @return bool
		 */
		static function validate_property( $property, $level = 0 ) {

			$valid = true;

			$property_value = $property->value;

			if ( $property->value instanceof Object ) {

				$valid = static::validate_object( $property_value, $level );

			} else if ( $property->required && empty( $property_value ) ) {

				$unique_id = Util::unique_id( $property->parent_object );

				$error_msg = sprintf( "Property \"{$property->property_name}\" is required for the \"%s\" %s", $unique_id, get_class( $property->parent_object ) );

				if ( is_array( $property->value ) && 0 === count( $property_value ) ) {

					self::$errors[] = "{$error_msg} to have array elements.";
					$valid = false;

				} else if ( is_null( $property_value ) ) {

					self::$errors[] = "{$error_msg}.";
					$valid = false;

				}

			}

			return $valid;

		}

	}

}
