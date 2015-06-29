<?php

namespace JSON_Loader {

	use JSON_Loader;

	abstract class Generator extends Base {

		const SLUG = 'generator';

		/**
		 * @var \JSON_Loader\Object Root object
		 */
		static $root;

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

			$this->initialize( $object, $parent, $args );

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
		 * @param Object $object
		 * @param string|Generator $generator
		 */
		static function generate( $object, $generator = 'Root_Generator' ) {

			if ( ! isset( self::$root ) ) {

				self::$root = $object;

			}

			if ( is_string( $generator ) ) {
				/**
				 * Must be a classname
				 */
				$generator_class = \JSON_Loader::get_qualified_class_name(
					$generator,
					\JSON_Loader::get_namespace( $object )
				);

				if ( ! class_exists( $generator_class ) ) {

					Loader::log_error( "Class {$generator_class} does not exist." );

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
		 *      @type string|bool $generator_class
		 * }
		 */
		function register_generator( $generator_slug, $value, $args = array() ) {

			$args = array_merge( array(
				'generator_class' => false,
				'template_file'   => false,
				'element_slug'    => false,
				'property_name'   => \JSON_Loader::underscorify( $generator_slug ),
			), $args );

			$generator_slug = \JSON_Loader::dashify( $generator_slug );

			if ( is_array( $value ) ) {

				if ( ! $args[ 'generator_class' ] ) {

					/**
					 * Get the class name of first element, if an array;
					 */
					 if ( count( $value ) && is_object( reset( $value ) ) ) {

					    if ( ! $args[ 'element_slug' ] ) {

						    $error_msg = "No 'element_slug' defined for generator {$generator_slug} in %s.";
					        Loader::log_error( sprintf( $error_msg, get_class( $this ) ) );

					    }

						$args[ 'generator_class' ] = $this->get_generator_class(
							$args[ 'element_slug' ],
							\JSON_Loader::get_namespace( $this )
						);

					 } else {

						 $args[ 'generator_class' ] = null;

					 }

				}

			}

			$generator_class =  $args[ 'generator_class' ];

			unset( $args[ 'generator_class' ] );

			if ( $value instanceof Object ) {

				do {

					if ( $generator_class && class_exists( $generator_class ) ) {

						break;

					}

					$namespace = \JSON_Loader::get_namespace( $value );
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

					Loader::log_error( "None of theses generator classes exist for generator slug \"{$generator_slug}\":"
						. " [{$generator_class}], [{$try_class1}] nor [{$try_class1}]."
					);

				} while ( false );

			}

            if ( is_array( $value ) ) {

	            $this->generators[ $generator_slug ] = new Array_Generator( $generator_class, $value, $this, array(

		            'property_name' => \JSON_Loader::underscorify( $args[ 'element_slug' ] ),

	            ));


            } else if ( get_class( $this ) === ltrim( $generator_class,'\\' ) ) {
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

			$this->output_files[ $template_type ] = $file_template;

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
			     ! method_exists( $this, 'register' ) ) {

				$error_msg = sprintf( 'Generator class %s does not have a callable register() method.', get_class( $this ) );

				Loader::log_error( $error_msg );

			}

			/**
			 * Call the Generator->register()
			 */
			call_user_func( $register_callable );

			if ( count( $dirs = $this->dirs ) ) {

				$properties = $this->get_state_properties();

				/**
				 * Make any subdirectories
				 */
				foreach( $dirs as $dir ) {

					$dir = $this->apply_file_template( $dir, $properties );

					self::mkdir( $dir );

				}

			}

			if ( count( $files = $this->output_files ) ) {

				foreach( $files as $template_type => $file_template ) {

					$this->generate_file( $file_template, $template_type );

				}
			}

			if ( count( $generators = $this->generators ) ) {

				foreach( $generators as $generator_slug => $generator ) {

					$generator_slug = \JSON_Loader::underscorify( $generator_slug );

					if ( ! is_array( $generator ) ) {

						/**
						 * @var Generator $generator
						 */
						self::generate( $this->object->$generator_slug, $generator );

					} else {

						$values = $this->object->$generator_slug;

						foreach( $generator as $index => $element_generator ) {

							self::generate( $values[ $index ], $element_generator );

						}

					}

				}
			}

		}

		/**
		 * @param bool|string $path
		 * @return string
		 */
		function template_dir( $path = false ) {

			$reflector = new \ReflectionClass( $this );

			$template_dir = dirname( dirname( $reflector->getFileName() ) ) . '/templates';

			return $path ? rtrim( "{$template_dir}/{$path}", '/' ) : $template_dir;

		}

		/**
		 * @param string $file_template
		 * @param string $template_type
		 */
		function generate_file( $file_template, $template_type ) {

			$filepath = $this->apply_file_template( $file_template,  $properties = $this->get_state_properties() );

			if ( ! is_file( $filepath ) ) {

				if ( ! is_file( $template_file = $this->template_dir( "{$template_type}.php" ) ) ) {

					Loader::log_error( "Template file {$this->template_file} does not exist." );

				}

				$generator_slug = static::generator_slug();

				$object_name = \JSON_Loader::underscorify( $generator_slug );

				extract( array( $object_name => $this->object ), EXTR_SKIP );

				unset( $file_template, $generator_slug );

				ob_start();

				require( $template_file );

				$source = ob_get_clean();

				/**
				 * @todo Change this to generate other types of files beside PHP.
				 */
				file_put_contents( $filepath, "<?php\n{$source}" );

			}

		}

		/**
		 * @param string $generator_slug
		 * @param string $namespace
		 *
		 * @return bool
		 */
		function get_generator_class( $generator_slug, $namespace ) {

			$generator_slug = implode( '_', array_map( 'ucfirst', explode( '_', \JSON_Loader::underscorify( $generator_slug ) ) ) );

			$generator_class = \JSON_Loader::get_qualified_class_name( "{$generator_slug}_Generator", $namespace );

			if ( ! class_exists( $generator_class ) ) {

				Loader::log_error( "The generator class {$generator_class} is not a valid class or it's filename"
					. " is malformed for the autoloader; should be /generators/{$generator_slug}-generator.php" );

			}

			return $generator_class;

		}
		/**
		 * @param $file_template
		 * @param $properties
		 *
		 * @return mixed
		 */
		function apply_file_template( $file_template, $properties ) {

			$messages = array();

			$values = array();

			if ( preg_match_all( '#(.*?)\{([^\}]+)\}#', $file_template, $matches ) ) {

				$root_state = Loader::get_state( self::$root );

				$root_properties = $root_state->values;

				foreach ( $matches[2] as $template_var ) {

					$chain = explode( '->', $template_var );

					$counter = count( $chain );

					$value = null;

					foreach( $chain as $index => $property_name ) {

						if ( ! is_array( $properties ) ) {

							$messages[] = sprintf( "Template var {$template_var} does not match schema for {$property_name};"
								." \\JSON_Loader\\Object expected but %s provided.", \JSON_Loader::get_type( $properties ) );
							break;

						}

						$has_property = array_key_exists( $property_name, $properties );
						$has_root_property = 0 === $index && array_key_exists( $property_name, $root_properties );

						if ( 1 == $counter ) {

							$parent_value = $value;

							if ( $has_property ) {

								$value = $properties[ $property_name ];

							} else if ( $has_root_property ) {

								$value = $root_properties[ $property_name ];
							}

						} else {

							if ( ! $has_property && ! $has_root_property ) {

								$messages[] = "Template var {$template_var} does not match schema for {$property_name}.";

							}
							if ( $has_property && ! is_object( $value = $properties[ $property_name ] ) ) {

								$messages[] = "Property {$property_name} for template var {$template_var} is not an object.";

							}
							if ( $has_root_property && ! is_object( $value = $root_properties[ $property_name ] ) ) {

								$messages[] = "Property {$property_name} for template var {$template_var} is not an object.";

							}

							$properties = Loader::get_state( $value )->values;

							$counter--;

						}

					}

					$non_castable = "Property {$property_name} for template var {$template_var} is an {type}; needs to be castable to a string.";
					if ( is_array( $value ) ) {
						$messages[] = str_replace( '{type}', 'array', $non_castable );
					}
					if ( is_object(  $value ) && '' === @(string)$property ) {
						$messages[] = str_replace( '{type}', 'object', $non_castable );
					}

					if ( is_null( $parent_value ) ) {

						if ( $has_property ) {

							$values[ $template_var ] = $this->object->$property_name;

						} else if ( $has_root_property ) {

							$values[ $template_var ] = self::$root->$property_name;

						}

					} else if ( $parent_value instanceof Object ) {

						$values[ $template_var ] = $parent_value->$property_name( $value );

					} else {

						Loader::log_error( sprintf(
							"{$property_name} is not a valid property of class %s.",
							\JSON_Loader::get_type( $object )
						));

					}

				}

			}
			if ( count( $messages ) ) {

				Loader::log_error( implode( "\n\t- ", $messages ) );

			} else {

				$filepath = $file_template;

				foreach ( $values as $template_var => $value ) {

					$filepath = str_replace( '{' . $template_var . '}', $value, $filepath );

				}

			}
			return $filepath;

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
		 * @return Property[] $object
		 */
		function get_state_properties() {

		 	return Loader::get_state_properties( $this->object );

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

	}

}
