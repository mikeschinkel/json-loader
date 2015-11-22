<?php

namespace JsonLoader {

	class Output {

		/**
		 * @param Object $object
		 * @param integer $level
		 */
		static function show_data( $object, $level = 0 ) {

			$properties = Util::get_schema_values( $object );
			$width      = 0;
			$rearranged = array();
			foreach ( $properties as $field => $value ) {

				$width = max( strlen( $field ), $width );

				if ( is_object( $value ) || ( is_array( $value ) && count( $value ) ) ) {
					/**
					 * Move these to the end of the list of properties
					 */
					$rearranged[ $field ] = $value;
					unset( $properties[ $field ] );

				}
			}
			$properties = $properties + $rearranged;

			foreach ( $properties as $field => $value ) {

				if ( method_exists( $object, $field ) ) {
					$value = $object->$field( $value );
				}

				self::_echo_pad( $level, $field, $width );

				if ( $value instanceof Object ) {

					static::show_data( $value, 1 + $level );

				} else if ( is_array( $value ) ) {

					if ( 0 == count( $value ) ) {

						echo 'array()';

					} else {

						foreach ( $value as $index => $object ) {

							self::_echo_pad( 1 + $level, "[{$index}]", $width );
							static::show_data( $object, 2 + $level );

						}

					}

				} else if ( is_bool( $value ) ) {

					echo $value ? 'true' : 'false';

				} else if ( is_null( $value ) ) {

					echo 'null';

				} else {

					echo $value;

				}

			}

		}

		/**
		 * @param integer $level
		 * @param $string
		 * @param $width
		 */
		private static function _echo_pad( $level, $string, $width ) {

			$indent = str_repeat( "\t", $level );
			echo "\n{$indent}" . str_pad( "{$string}: ", 2 + $width );

		}

	}
}
