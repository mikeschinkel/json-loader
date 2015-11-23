<?php

namespace JsonLoader {

	use JsonLoader;

	/**
	 * Class Generator
	 *
	 * @package JsonLoader
	 *
	 * @property string $object_unique_id
	 */
	abstract class Generator extends Base {

		const SLUG = 'generator';

		const TAB_WIDTH = 4;

		/**
		 * @var array Files to generate
		 */
		var $output_files = array();

		/**
		 * @var Generator[] Generators registered
		 */
		var $generators = array();

		/**
		 * @var array Subdirectories for a Generator
		 */
		var $dirs = array();

		/**
		 * @var \JsonLoader\Object|array
		 */
		var $object;

		/**
		 * @var string
		 */
		var $property_name;

		/**
		 * @var@var string
		 */
		var $element_slug;

		/**
		 * @var Generator
		 */
		var $parent;

		/**
		 * @param Object $object
		 * @param Generator $parent
		 * @param array $args
		 */
		function __construct( $object, $parent, $args = array() ) {

			$this->initialize( $object, $parent );

			parent::__construct( $args );
		}

		/**
		 * @param \JsonLoader\Object $object
		 * @param Generator $parent
		 */
		static function generate( $object, $parent = null ) {

			if ( is_null( $parent ) ) {

				$object_class = get_class( $object );

				$generator_class = "{$object_class}_Generator";

				if ( ! class_exists( $generator_class, true ) ) {

					Util::log_error( "Class {$generator_class} does not exist." );

				}

				/**
				 * @var Generator $generator
				 */
				$parent = new $generator_class( $object, $parent );

			}

			$parent->execute();

		}

		/**
		 * @param Object $object
		 * @param Generator $parent
		 */
		function initialize( $object, $parent ) {

			$this->object = $object;

			$this->parent = $parent;

		}

		/**
		 * Stub method so child can safely call its object_parent
		 * Might want to put something here later, though.
		 */
		function register() {

		}


		/**
		 * @return string
		 */
		function object_unique_id() {

			return $this->object->get_unique_id();

		}

		/**
		 * Returns a file name prefixed with a slug and dashified.
		 *
		 * @param boolean|string $suffix
		 * @return string
		 */
		function filenameify( $suffix = false ) {

			$suffix = rtrim( $suffix, '-_ ' );

			return Util::dashify( static::SLUG . ( $suffix ? "-{$suffix}" : '' ) ) ;

		}

		/**
		 * @param string $constant_name
		 *
		 * @return mixed|null
		 */
		function get_constant( $constant_name ) {

			return Util::get_constant( $constant_name, get_class( $this ) );

		}

		/**
		 * @param string $generator_slug
		 * @param \JsonLoader\Object|array $value
		 * @param array $args {
		 *
		 * @type string|boolean $generator_class
		 * }
		 */
		function register_generator( $generator_slug, $value, $args = array() ) {

			$args = Util::parse_args( $args, array(
				'generator_class' => false,
				'element_slug'    => false,
				'property_name'   => Util::underscorify( $generator_slug ),
			));

			$generator_slug = Util::dashify( $generator_slug );

			if ( is_array( $value ) ) {

				if ( ! $args['generator_class'] ) {

					/**
					 * Get the class name of first element, if an array;
					 */
					if ( count( $value ) && is_object( reset( $value ) ) ) {

						if ( ! $args['element_slug'] ) {

							$error_msg = "No 'element_slug' defined for generator {$generator_slug} in %s.";
							Util::log_error( sprintf( $error_msg, get_class( $this ) ) );

						}

						$args['generator_class'] = $this->get_generator_class(
							$args['element_slug'],
							Util::get_namespace( $this )
						);

					} else {

						$args['generator_class'] = null;

					}

				}

			}

			$generator_class = $args['generator_class'];

			unset( $args['generator_class'] );

			if ( $value instanceof Object ) {

				do {

					if ( $generator_class && class_exists( $generator_class ) ) {

						break;

					}

					$namespace  = Util::get_namespace( $value );
					$try_class1 = "\\{$namespace}\\{$generator_class}";

					if ( class_exists( $try_class1 ) ) {
						/**
						 * We must test for \ in class because we can get false positives otherwise
						 */
						$generator_class = $try_class1;
						break;

					}

					$try_class2 = $this->get_generator_class( $generator_slug, $namespace );

					if ( class_exists( $try_class2 ) ) {

						$generator_class = $try_class2;
						break;

					}

					Util::log_error( "None of theses generator classes exist for generator slug \"{$generator_slug}\":"
					                 . " [{$generator_class}], [{$try_class1}] nor [{$try_class1}]."
					);

				} while ( false );

			}

			if ( is_array( $value ) ) {

				$this->generators[ $generator_slug ] = new ArrayGenerator( $generator_class, $value, $this, array(

					'property_name' => Util::underscorify( $args['element_slug'] ),

				) );

			} else if ( get_class( $this ) === ltrim( $generator_class, '\\' ) ) {
				/**
				 * This is an array element and the generator is defining itself
				 */

				/**
				 * @var Object $value
				 */
				$this->initialize( $value, $this );
				$this->set_args( $args );

			} else {
				$this->generators[ $generator_slug ] = new $generator_class( $value, $this, $args );

			}

		}

		/**
		 * @param string $template_type
		 * @param string $file_template
		 */
		function register_output_file( $template_type, $file_template ) {

			$this->output_files[ Util::dashify( $template_type ) ] = $file_template;

		}

		/**
		 * @param string $dir
		 */
		function register_dir( $dir ) {

			$this->dirs[] = $dir;

		}

		/**
		 * @param string|array $dirs
		 */
		function register_dirs( $dirs ) {

			if ( ! is_array( $dirs ) ) {

				$dirs = array( $dirs );

			}

			$dirs = array_map( function( $dir ) {

				return getcwd() . $dir;

			}, $dirs );

			$this->dirs = $dirs;

		}

		/**
		 *
		 */
		function execute() {

			if ( ! is_callable( $register_callable = array( $this, 'register' ) ) ||
			     ! method_exists( $this, 'register' )
			) {

				$error_msg = sprintf( 'Generator class %s does not have a callable register() method.', get_class( $this ) );

				Util::log_error( $error_msg );

			}

			/**
			 * Call the Generator->register()
			 */
			call_user_func( $register_callable );

			if ( count( $dirs = $this->dirs ) ) {

				/**
				 * Make any subdirectories
				 */
				foreach ( $dirs as $dir ) {

					Util::mkdir( $dir );

				}

			}

			if ( count( $files = $this->output_files ) ) {

				foreach ( $files as $template_type => $file_template ) {

					$this->generate_file( $file_template, $template_type );

				}
			}

			if ( count( $generators = $this->generators ) ) {

				foreach ( $generators as $generator_slug => $generator ) {

					$value = $this->get_object_value( Util::underscorify( $generator_slug ) );
					self::generate( $value, $generator );

				}

			}

		}

		/**
		 * @param boolean|string $path
		 *
		 * @return string
		 */
		function get_template_dir( $path = false ) {

			$template_dir = Util::get_template_dir( $this );

			return $path ? rtrim( "{$template_dir}/{$path}", '/' ) : $template_dir;

		}

		/**
		 * @param string $file_template
		 * @param string $template_type
		 */
		function generate_file( $file_template, $template_type ) {

			$filepath = $file_template;

			if ( ! is_file( $filepath ) ) {

				if ( ! is_file( $template_file = $this->get_template_dir( "{$template_type}.php" ) ) ) {

					Util::log_error( "Template file {$template_file} does not exist." );

				}

				$generator_slug = static::generator_slug();

				$object_name = Util::underscorify( $generator_slug );

				extract( array(
					$object_name => $this->object,
					'generator'  => $this,
				), EXTR_SKIP );

				unset( $file_template, $generator_slug );

				ob_start();

				require( $template_file );

				$source = ob_get_clean();

	// @todo which of '#ims' do we need?

				if ( preg_match_all( "#(.*?)(\n+(\s+))?\[\s*@include\s*\(\s*(.+?)\s*\)\s*\]#ims", "{$source}[@include(~~~)]", $matches, PREG_SET_ORDER ) ) {

					if ( 1 < count( $matches[ 0 ] ) ) {

						$include = array();

						foreach ( $matches as $match ) {

							/**
							 * Capture what comes before the [@include({filename})]
							 */
							$include[] = $match[ 1 ];

							if ( '~~~' !== $match[ 4 ] ) {

								ob_start();

								/**
								 * Capture what is in /includes/{filename}.php
								 */
								require( dirname( $template_file ) . "/includes/{$match[4]}.php" );

								$include[] = $match[ 2 ] . ltrim( implode( array_map( function( $line ) use( $match ) {
									return "{$match[ 3 ]}{$line}";
								}, explode( "\n", ob_get_clean() ) ) ) );


							}

						}

						$source = implode( $include );

					}

				}

				Util::ensure_dir( dirname( $filepath ) );

				file_put_contents( $filepath, $source );

			}

		}

		/**
		 * @param string $generator_slug
		 * @param string $namespace
		 *
		 * @return boolean
		 */
		function get_generator_class( $generator_slug, $namespace ) {

			$generator_slug = implode( '_', array_map( 'ucfirst', explode( '_', Util::underscorify( $generator_slug ) ) ) );

			$generator_class = Util::get_qualified_class_name( "{$generator_slug}_Generator", $namespace );

			if ( ! class_exists( $generator_class ) ) {

				Util::log_error( "The generator class {$generator_class} is not a valid class or it's filename"
				                 . " is malformed for the autoloader; should be /generators/{$generator_slug}-generator.php" );

			}

			return $generator_class;

		}

		/**
		 * @return string|null
		 */
		function generator_slug() {

			$generator_slug = constant( get_class( $this ) . '::SLUG' );

			if ( empty( $generator_slug ) ) {

				$generator_slug = 'unspecified';

			}

			return $generator_slug;

		}

		/**
		 * @param Property[] $properties
		 * @param array $args {
		 *      @type integer|null $tab_count
		 *      @type string $trim
		 * }
		 *
		 * @return string
		 */
		function get_php_initializers( $properties, $args = array() ) {

			static $depth = 0;

			if ( 0 === $depth++ ) {
				/*
				 * Many properties can accept defaults.
				 * So remove them from the array so we
				 * don't have to generate them redundantly.
				 */
				$properties = $this->filter_redundant( $properties );
			}


			$args = Util::parse_args( $args, array(
				'tab_count' => 3,
				'trim'      => 'trim',
			));

			$output = array();

			$tabs = str_repeat( "\t", $args[ 'tab_count' ] );

			/**
			 * Find the longest name so we can later pad between the
			 * single-quoted name and the => operator to align the values.
			 */
			$max_name_length = 0;

			$tracker        = 0;
			$is_associative = false;
			foreach ( array_keys( $properties ) as $index ) {

				if ( $index !== $tracker ++ ) {
					$is_associative = true;
					break;
				}

			}

			/**
			 * @var Property $property
			 */
			foreach ( $properties as $property_name => $property ) {

				if ( $property instanceof Property ) {

					$value = $this->get_object_value( $property_name );

					$value = $property->cast( $value );

				} else {
					/*
					 * For when called recursively on numerically-indexed
					 * or other non-property arrays
					 */
					$value = $property;
				}


				if ( is_null( $value ) ) {

					continue;

				}

				$prefix = $is_associative ? "{$tabs}'{$property_name}' => " : $tabs;

				switch ( gettype( $value ) ) {

					case 'string':

						$output[] = "{$prefix}'{$value}',";

						break;

					case 'integer':

						if ( 0 === $value && $property->is_true( 'omit_if_zero' ) ) {

							continue;

						}

						$output[] = "{$prefix} {$value},";

						break;

					case 'boolean':

						$bool     = $value ? 'true' : 'false';
						$output[] = "{$prefix} {$bool},";

						break;

					case 'array':

						$values = $this->get_php_initializers( (array) $value, array(
							'tab_count' => 1 + $args[ 'tab_count' ],
							'trim' => 'notrim',
						));

						$output[] = "{$prefix} array(\n{$values}\n{$tabs}),";

						break;

					case 'object':

						$as_array = $this->get_php_initializers( (array) $value, $args );

						$output[] = preg_replace( "#(=>\s+)(array\()#", '=>$1(object) $2', $as_array );
						break;

					default:

						break;

				}

				$max_name_length = max( strlen( $property_name ), $max_name_length );

			}

			if ( $extra = $max_name_length % 4 ) {

				$max_name_length += 4 - $extra;

			}

			foreach ( $output as $index => $line ) {

				/**
				 * Calculate the padding were four spaces get a tab between the
				 * single-quoted name and the => operator to align the values.
				 */
				$whitespace       = $max_name_length - strpos( trim( $line ), '=>' ) + self::TAB_WIDTH;
				$extra_tab        = 0 !== $whitespace % self::TAB_WIDTH;
				$num_tabs         = $whitespace >= self::TAB_WIDTH ? floor( $whitespace / self::TAB_WIDTH ) : 0;
				$padding          = str_repeat( "\t", $num_tabs ) . ( $extra_tab ? "\t" : '' );
				$output[ $index ] = str_replace( '=>', "{$padding}=>", $output[ $index ] );

			}

			$output = implode( "\n", $output );

			$depth--;

			return 'trim' === $args[ 'trim' ] ? ltrim( $output ) : $output;

		}

		/**
		 * @param string $method_name
		 *
		 * @return boolean
		 */
		function has_method( $method_name ) {

			return method_exists( $this, $method_name ) && is_callable( array( $this, $method_name ) );

		}
		/**
		 * @param string $method_name
		 *
		 * @return boolean
		 */
		function call_method( $method_name ) {

			if ( 0 === count( $args = array_slice( func_get_args(), 1 ) ) ) {

				$value = call_user_func( array( $this, $method_name ) );

			} else {

				$value = call_user_func_array( array( $this, $method_name ), $args );

			}

			return $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed|null
		 */
		function __get( $property_name ) {

			$value = null;

			Util::push_class( $this );

			if ( $this->has_method( $property_name ) ) {

				$value = $this->call_method( $property_name );

			} else if ( $this->has_object_property( $property_name ) ) {

				$value = $this->get_object_value( $property_name );

			} else if ( $this->parent->has_object_property( $property_name ) ) {

				$value = $this->parent->get_object_value( $property_name );

			} else {

				$class_name = implode( ', ', array_unique( Util::class_stack() ) );

				if ( empty( $class_name ) ) {

					$class_name = get_class( $this );

				}

				Util::log_error( "There is no property \"{$property_name}\" in any of these class(es): {$class_name}." );

			}

			Util::pop_class();

			return $value;

		}

		/**
		 * @param string $property_name
		 *
		 * @return boolean
		 */

		function has_object_property( $property_name ) {

			return $this->object->has_property( $property_name ) || $this->object->has_method( $property_name );

		}

		/**
		 * @param string $property_name
		 *
		 * @return mixed
		 */
		function get_object_value( $property_name ) {

			$object = $this->object;

			$value = $object->has_property( $property_name )
				? $object->get_value( $property_name )
				: null;

			if ( $object->has_method( $property_name ) ) {

				$value = $object->call_method( $property_name, $value );

			}

			if ( $object->get_elements_instantiated( $property_name ) ) {

			 	$object->set_cached( $property_name, $value );
			}


			return $value;

		}

		/**
		 * @return array
		 */
		function php_initializers() {

			return $this->get_php_initializers( $this->initializer_properties() );

		}

		/**
		 * @return Property[]
		 */
		function initializer_properties() {

			return $this->get_initializer_properties( $this->object_properties() );

		}

		/**
		 * @return string
		 */
		function this_dir() {

			return $this->object->get_filepath();

		}

		/**
		 * @return array
		 */
		/**
		 * @param Property[]|boolean|false $properties
		 *
		 * @return Property[]
		 */
		function get_initializer_properties( $properties = false ) {

			$initializers = array();

			foreach ( $properties as $property_name => $property ) {

				if ( $property->initializer ) {

					$initializers[ $property_name ] = $property;

				}

			}

			return $initializers;

		}

		/**
		 * @return Property[]
		 */
		function object_properties() {

			return $this->object->get_properties();

		}

		/**
		 * @param string $property_name
		 * @param mixed $value
		 */
		function set_object_value( $property_name, $value ) {

			$this->object->set_value( $property_name, $value );

		}


		/**
		 * Filters values out that match the core default for the underlying platform.
		 *
		 * @return array
		 *
		 * @example
		 *
		 *      No value provided @default=true  @missing=false => include in returned $args
		 *      No value provided @default=false @missing=false => DO NOT include in returned $args
		 *      true provided     @default=true  @missing=false => include in returned $args
		 *      true provided     @default=true  @missing=true  => DO NOT include in returned $args
		 *      true provided     @default=false @missing=true  => DO NOT include in returned $args
		 *
		 * @param Property[]|boolean $properties
		 *
		 * @return boolean|JsonLoader\Property[]
		 */
		function filter_redundant( $properties = false ) {

		    if ( ! $properties ) {

			    $properties = $this->object_properties();

		    }

			foreach ( $properties as $property_name => $property ) {

				$property_value = $this->get_object_value( $property_name );

				$missing_value = ! is_null( $property->missing )
					? $property->cast( $property->missing )
					: null;

				if ( is_null( $property_value ) && is_null( $missing_value ) ) {

					unset( $properties[ $property_name ] );
					continue;

				} else if ( ! preg_match( '#^(!?)\s*\$(\w+)$#', $missing_value, $matches ) ) {

					$default_value = $missing_value;

				} else {

					if ( ! $this->has_object_property( $default_property_name = $matches[2] ) ) {

						$err_msg = 'Property %s not declared but referenced in a @%s for %s yet. Must declare before referencing.';

						Util::log_error( sprintf( $err_msg,
							$default_property_name,
							'missing',
							$property_name
						));

					} else {

						$default_value = '!' === $matches[1]
							? ! $this->get_object_value( $default_property_name )
							: $this->get_object_value( $default_property_name );

					}

				}


				if ( is_array( $property_value ) && is_string( $missing_value ) && $property->explode ) {
					/*
					 * If the value is an already exploded array but the missing value is not exploded
					 * the implode the value to compare with the missing value.
					 */
					$property_value = implode( $property->explode, $property_value );

				}

				if ( $default_value === $property_value ) {
					/*
					 * If the default value and the property value are the same
					 * the we don't need to generate an initializer for them.
					 */
					unset( $properties[ $property_name ] );

				}

			}

			return $properties;

		}

	}

}
