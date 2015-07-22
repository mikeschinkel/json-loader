<?php

namespace JSON_Loader {

	use JSON_Loader;

	/**
	 * Class Generator
	 *
	 * @package JSON_Loader
	 *
	 * @property string $root_dir
	 * @property string $root_object
	 * @property string $unique_id
	 */
	abstract class Generator extends Base {

		const SLUG = 'generator';

		const TAB_WIDTH = 4;

		/**
		 * @var \JSON_Loader\Object Root object
		 */
		static $root_generator;

		/**
		 * @var array Files to generate
		 */
		var $output_files = array();

		/**
		 * @var array Generators registered
		 */
		var $generators = array();

		/**
		 * @var array Subdirectories for a Generator
		 */
		var $dirs = array();

		/**
		 * @var \JSON_Loader\Object|array
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

			if ( ! isset( self::$root_generator ) ) {

				self::$root_generator = $this;

			}

			$this->initialize( $object, $parent );

			parent::__construct( $args );
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
		 * @return State
		 */
		function object_state() {

			/**
			 * @var Object $object
			 */
			$object = $this->object;

			return Util::get_state( $object );

		}

		/**
		 * @param State $state
		 */
		function set_object_state( $state ) {

			/**
			 * @var Object $object
			 */
			$object = $this->object;

			Util::set_state( $object, $state );

		}

		/**
		 * @return string
		 */
		function unique_id() {

			return Util::unique_id( $this->object );

		}

		/**
		 * Returns a file name prefixed with a slug and dashified.
		 *
		 * @param bool|string $suffix
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
		 * @return Object
		 */
		function root_generator() {

			return self::$root_generator;

		}

		/**
		 * @return Object
		 */
		function root() {

			return Util::root();

		}

		/**
		 *
		 */
		function root_dir() {

			if ( ! ( $root_dir = Util::root()->root_dir ) ) {

				$root_dir = getcwd();

			} else {

				if ( ! preg_match( '#^(~|/)$#', $root_dir[0] ) ) {

					/**
					 * Normalize directory format.
					 *
					 * @todo Make work for Windows.
					 */

					$root_dir = "~/{$root_dir}";

				}

				$root_dir = preg_replace( '#^~/(.*)$#', getcwd() . '/$1', $root_dir );

			}
			return $root_dir;

		}

		/**
		 * @param Object $object
		 * @param string|Generator $generator
		 */
		static function generate( $object, $generator = 'Root_Generator' ) {

			if ( is_string( $generator ) ) {
				/**
				 * Must be a classname
				 */
				$generator_class = Util::get_qualified_class_name(
					$generator,
					Util::get_namespace( $object )
				);

				if ( ! class_exists( $generator_class ) ) {

					Util::log_error( "Class {$generator_class} does not exist." );

				}


				/**
				 * @var Generator $generator
				 */
				$generator = new $generator_class( $object, $generator );

			}

			$generator->execute();

		}

		/**
		 * @param string $generator_slug
		 * @param Object|array $value
		 * @param array $args {
		 *
		 * @type string|bool $generator_class
		 * }
		 */
		function register_generator( $generator_slug, $value, $args = array() ) {

			$args = array_merge( array(
				'generator_class' => false,
				'element_slug'    => false,
				'property_name'   => Util::underscorify( $generator_slug ),
			), $args );

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

				$this->generators[ $generator_slug ] = new Array_Generator( $generator_class, $value, $this, array(

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
		 * @param string|array $dirs
		 */
		function register_dirs( $dirs ) {

			if ( ! is_array( $dirs ) ) {

				$dirs = array( $dirs );

			}

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

					//@todo This was removed from logic before refactoring was complete. Would be nice to add back in.
					//$dir = $this->apply_file_template( $dir, $this->accessible_properties() );

					self::mkdir( $dir );

				}

			}

			if ( count( $files = $this->output_files ) ) {

				foreach ( $files as $template_type => $file_template ) {

					$this->generate_file( $file_template, $template_type );

				}
			}

			if ( count( $generators = $this->generators ) ) {

				foreach ( $generators as $generator_slug => $generator ) {

					$generator_slug = Util::underscorify( $generator_slug );

					if ( ! is_array( $generator ) ) {

						/**
						 * @var Generator $generator
						 */
						self::generate( $this->object->$generator_slug, $generator );

					} else {

						$values = $this->object->$generator_slug;

						foreach ( $generator as $index => $element_generator ) {

							self::generate( $values[ $index ], $element_generator );

						}

					}

				}
			}

		}

		/**
		 * @param bool|string $path
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
								require( dirname( $template_file ) . "/includes/{$match[ 4 ]}.php" );

								$include[] = $match[ 2 ] . ltrim( implode( "\n", array_map( function( $line ) use( $match ) {
									return "{$match[ 3 ]}{$line}";
								}, explode( "\n", ob_get_clean() ) ) ) );


							}

						}

						$source = implode( $include );

					}

				}

				file_put_contents( $filepath, $source );

			}

		}

		/**
		 * @param string $generator_slug
		 * @param string $namespace
		 *
		 * @return bool
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
		static function generator_slug() {

			$generator_slug = constant( get_called_class() . '::SLUG' );

			if ( empty( $generator_slug ) ) {

				$generator_slug = 'unspecified';

			}

			return $generator_slug;

		}

		/**
		 * @param string|array $dir
		 */
		static function mkdir( $dir = array() ) {

			$dirs = ! is_array( $dir ) ? array( $dir ) : $dir;

			foreach ( $dirs as $dir ) {

				if ( ! is_dir( $dir ) ) {

					mkdir( $dir, 0777, true );

				}

			}

		}

		/**
		 * @param Property[] $properties
		 * @param array $args {
		 *      @type int|null $tab_count
		 *      @type string $trim
		 * }
		 *
		 * @return string
		 */
		function get_php_initializers( $properties, $args = array() ) {

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

				$value = is_object( $property ) ? $property->value : $property;

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

						$values = $this->get_php_initializers( $value, array(
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

						echo '';
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

			return 'trim' === $args[ 'trim' ] ? ltrim( $output ) : $output;

		}


		/**
		 * @param string $property_name
		 *
		 * @return mixed|null
		 */
		function __get( $property_name ) {

			$value = null;

			Util::push_class( $this );

			if ( Util::can_call( $callable = array( $this, $property_name ) ) ) {

				/**
				 * Try calling its own methods first
				 */
				$value = call_user_func( $callable );

			} else if ( Util::has_property( $this->object, $property_name ) ) {

				$value = $this->object->$property_name;

			} else {

				$class_name = implode( ', ', Util::class_stack() );

				if ( empty( $class_name ) ) {

					$class_name = get_class( $this );

				}

				Util::log_error( "There is no property \"{$property_name}\" in any of these class(es): {$class_name}." );

			}

			Util::pop_class();

			return $value;

		}



		/**
		 * @return array
		 */
		function php_initializers() {

			return $this->get_php_initializers( $this->initializer_properties() );

		}


		/**
		 * @return array
		 */
		function initializer_properties() {

			$properties = Util::get_initializer_properties( $this->object );

			$properties = Util::filter_default_values( $properties, 'default' );

			return Util::explode_args( $this->object, $properties );

		}

	}

}
