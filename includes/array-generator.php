<?php

namespace JSON_Loader {

	/**
	 * Class Array_Generator
	 */
	class Array_Generator {

		var $generator_class;

		var $values;

		var $parent;

		var $args;

		/**
		 * @param string $generator_class
		 * @param array $values
		 * @param Generator $parent
		 * @param array $args
		 */
		function __construct( $generator_class, $values, $parent, $args ) {

			$this->generator_class = $generator_class;
			$this->values          = $values;
			$this->parent          = $parent;
			$this->args            = $args;

		}

		function execute() {

			$generator_class = $this->generator_class;

			$prior_value = new \stdClass();

			foreach( $this->values as $index => $value ) {

				/**
				 * @var Generator $generator
				 */
				$generator = new $generator_class( $value, $this->parent, $this->args );

				$state = Loader::get_state( $value );

				$values = $state->values;

				$generator->generate( $value, $generator );

				$prior_value = $value;

			}

		}

//		/**
//		 * @param string $property_name
//		 *
//		 * @return string
//		 */
//		function __get( $property_name ) {
//
//			return $this->generator->$property_name;
//
//		}
//
//		/**
//		 * @param string $property_name
//		 * @param mixed $value
//		 */
//		function __set( $property_name, $value ) {
//
//			$this->generator->$property_name = $value;
//
//		}
//
//		/**
//		 * @param string $method_name
//		 * @param array $args
//		 *
//		 * @return mixed
//		 */
//		function __call( $method_name, $args ) {
//
//			return call_user_func_array( array( $this->generator, $method_name ), $args );
//
//		}
//

	}

}


