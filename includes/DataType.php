<?php

/**
 * Namespace JsonLoader
 */
namespace JsonLoader {

	use JsonLoader;

	/**
	 * Class Property
	 *
	 * @package JsonLoader
	 */
	class DataType extends Base {

		/**
		 * @var string DataType as PHPDocumented
		 */
		var $doc_type = 'string';

		/**
		 * @var string Namespace for this data type
		 */
		var $namespace;

		/**
		 * @var string Base type, i.e. string|boolean|integer|object|array
		 */
		var $base_type = 'string';

		/**
		 * @var boolean|string  Array element base type if $base_type is 'array'
		 */
		var $array_of = null;

		/**
		 * @var boolean|string Qualified class name if $base_type is 'object' and namespaced
		 */
		var $class_name = null;

		/**
		 * @var boolean|string Local class name if $base_type is 'object' and namespaced
		 */
		var $local_class = null;

		/**
		 * @param string $type
		 * @param array $args
		 *
		 * @return static
		 */
		static function make_new( $type, $args = array() ) {

			// @todo Reuse data types that match the data type to be created.

			return new static( $type, $args );

		}

		/**
		 * @param string $type
		 * @param array $args
		 */
		function __construct( $type, $args = array() ) {

			$args[ 'doc_type' ] = $type;

			if ( ! Util::is_builtin_type( $type ) ) {

				$args[ 'class_name' ] = $type;
				$args[ 'local_class' ] = Util::parse_local_class( $type );
				$args[ 'namespace' ] = isset( $args['namespace'] )
					? $args['namespace']
					: Util::parse_namespace( $type );

				if ( preg_match( '#(.*?)\[\]$#', $type, $match ) ) {

					$args[ 'base_type' ]  = 'array';
					$args[ 'array_of' ]   = 'object';
					$args[ 'class_name' ] = $match[1];

				} else {

					$args[ 'base_type' ]  = 'object';

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
		 * @param DataType $type
		 * @param null|mixed $value
		 *
		 * @return boolean
		 */
		function is_equal( $type, $value = null ) {

			$type_as_string = (string)$type;
			$this_as_string = (string)$this;

			if ( 'array' === $type_as_string && 'array' === $this->base_type ) {

				$element_type = new DataType( gettype( reset( $value ) ) );

				if ( 'mixed' === $element_type->array_of ) {

					$is_equal = true;

				} else if ( $this->array_of === $element_type->base_type ) {

					$is_equal = true;

				} else {

					$is_equal = false;
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
		 * @return boolean|null|string
		 */
		function element_type() {

			return $this->array_of
				? ( 'object' === $this->array_of
					? new DataType( $this->class_name, $this->namespace )
					: $this
				)
				: null;
		}

	}

}
