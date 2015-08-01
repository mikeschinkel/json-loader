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

			foreach( $this->values as $index => $value ) {

				if ( 0 < $index ) {
					/**
					 * @todo Yes, this is really screwy. We'd need to rearchitect to fix it eventually.
					 */
					$s = Util::get_state( $value );
					Util::get_state( $s->owner )->set_values( $s->values() );
				}

				Generator::generate( $value, new $generator_class( $value, $this->parent, $this->args ) );

			}

		}

	}

}


