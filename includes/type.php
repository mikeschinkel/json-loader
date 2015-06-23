<?php

/**
 * Namespace JSON_Loader
 */
namespace JSON_Loader {

	use JSON_Loader;

	/**
	 * Class Property
	 *
	 * @package JSON_Loader
	 */
	class Type extends Base {

		/**
		 * @var string Type as PHPDocumented
		 */
		var $doc_type = 'string';

		/**
		 * @var string Type Namespace for this type
		 */
		var $namespace;

		/**
		 * @var string Base type, i.e. string|bool|int|object|array
		 */
		var $base_type = 'string';

		/**
		 * @var bool|string  Array element base type if $base_type is 'array'
		 */
		var $array_of = null;

		/**
		 * @var bool|string Qualified class name if $base_type is 'object'
		 */
		var $class_name = null;

		/**
		 * @var State
		 */
		var $parent;

		/**
		 * @param string $type
		 * @param string $namespace
		 * @param array $args
		 *
		 */
		function __construct( $type, $namespace, $args = array() ) {

			$args[ 'doc_type' ] = $type;
			$args[ 'namespace' ] = $namespace;

			if ( preg_match( '#^boolean(\[\])?$#', $type, $match ) ) {

				$type = 'bool' . ( isset( $match[ 1 ] ) ? '[]' : '' );

			}

			if ( ! preg_match( '#^(string|bool|int|object|array)#', $type ) ) {

				if ( preg_match( '#(.*?)\[\]$#', $type, $match ) ) {

					$args[ 'base_type' ]  = 'array';
					$args[ 'array_of' ]   = 'object';
					$args[ 'class_name' ] = \JSON_Loader::get_qualified_class_name( $match[1], $namespace );

				} else {

					$args[ 'base_type' ]  = 'object';
					$args[ 'class_name' ] = \JSON_Loader::get_qualified_class_name( $type, $namespace );

				}

			} else {

				$args[ 'base_type' ] = $type;

				if ( '[]' === ( substr( $type, - 2 ) ) ) {

					$args[ 'array_of' ]  = substr( $type, 0, - 2 );
					$args[ 'base_type' ] = 'array';

				} else if ( 'array' === $type ) {

					$args[ 'array_of' ] = 'mixed';

				}

			}

			parent::__construct( $args );

		}

		/**
		 * @return string
		 */
		function __ToString() {

			if ( $this->class_name && 'object' === $this->base_type ) {

				$type = $this->class_name . ( $this->array_of ? '[]' : '' );

			} else if ( $this->array_of ) {

				$type = $this->base_type . ( 'array' !== $this->base_type ? '[]' : '' );

			} else {

				$type = $this->base_type;

			}

			return $type;

		}

		/**
		 * @param Type $type
		 * @param null|mixed $value
		 * @return bool
		 */
		function is_equal( $type, $value = null ) {

			$type_as_string = (string)$type;
			$this_as_string = (string)$this;

			if ( 'array' === $type_as_string && 'array' === $this->base_type ) {

				$element_type = new Type( gettype( reset( $value ) ), $this->namespace );

				if ( 'mixed' === $element_type->array_of ) {

					$is_equal = true;

				} else if ( $this->array_of === $element_type->base_type ) {

					$is_equal = true;

				}

			} else if ( 'object' === $type_as_string && $this->class_name ) {

				$is_equal = true;

			} else if ( $type_as_string === $this_as_string ) {

				$is_equal = true;

			} else {

				$is_equal = false;

			}

			return $is_equal;

		}

		/**
		 * @return bool|null|string
		 */
		function element_type() {

			return $this->array_of
				? ( 'object' === $this->array_of
					? new Type( $this->class_name, $this->namespace )
					: $this
				)
				: null;
		}

	}

}
