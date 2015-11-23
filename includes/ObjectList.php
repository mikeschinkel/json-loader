<?php

namespace JsonLoader {

	/**
	 * Class ObjectList
	 *
	 * @package JsonLoader
	 *
	 */
	class ObjectList extends \ArrayObject {

		/**
		 * Process each element via a callback and implode the results.
		 *
		 * @param callback $callback
		 * @param array $args
		 * @return array|string
		 */
		function implode( $callback, $args = array() ) {

			return implode( $this->each( $callback, $args ) );

		}

		/**
		 * Process each element via a callback.
		 *
		 * @param callback $callback
		 * @param array $args
		 * @return array|string
		 */
		function each( $callback, $args = array() ) {

			$results = array();

			foreach( $this as $index => $item ) {

				$result = call_user_func( $callback, $item, $index, $args );

				if ( false === $result ) {

					break;

				}

				$results[] = $result;

			}

			return $results;

		}


	}
}
