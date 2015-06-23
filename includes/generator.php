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
		var $subdirs = array();

		/**
		 * @var \JSON_Loader\Object|array
		 */
		var $object;

		/**
		 * @param Object $object
		 * @param array $args
		 */
		function __construct( $object, $args = array() ) {

			$this->object = $object;

			parent::__construct( $args );

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
				$generator = new $generator_class( $object );

			}

			$generator->execute();

			echo "Generated.";

		}


		/**
		 * @param string $generator_slug
		 * @param Object|array $value
		 * @param string|bool $generator_class
		 */
		function register_generator( $generator_slug, $value, $generator_class = false ) {

			if ( is_array( $value ) ) {

				/**
				 * Get the first element if an array;
				 */
				$value = count( $value ) ? reset( $value ) : null;

			}

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

			$this->generators[ $generator_slug ] = new $generator_class( $value );

		}

		/**
		 * @param string $file_template
		 */
		function register_output_file( $file_template ) {

			$this->output_files[] = $file_template;

		}

		/**
		 * @param string|array $subdirs
		 */
		function register_subdirs( $subdirs ) {

			if ( ! is_array( $subdirs ) ) {

				$subdirs = array( $subdirs );

			}

			$this->subdirs[] = $subdirs;

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

			if ( count( $subdirs = $this->subdirs ) ) {

				$properties = $this->get_state_properties();

				/**
				 * Make any subdirectories
				 */
				foreach( $subdirs as $subdir ) {

					self::mkdir( $this->apply_file_template( $subdir, $properties ) );

				}

			}

			if ( count( $files = $this->output_files ) ) {

				foreach( $files as $file_template ) {

					$this->generate_file( $file_template );

				}
			}

			if ( count( $generators = $this->generators ) ) {

				foreach( $generators as $generator_slug => $generator ) {

					self::generate( $this->object->$generator_slug, $generator );

				}
			}

		}

		/**
		 * @param string $path
		 * @return string
		 */
		function template_dir( $path = false ) {

			$reflector = new \ReflectionClass( $this );

			$template_dir = dirname( dirname( $reflector->getFileName() ) ) . '/templates';

			return $path ? rtrim( "{$template_dir}/{$path}", '/' ) : $template_dir;

		}

		/**
		 * @param string $file_template
		 */
		function generate_file( $file_template ) {

			$filepath = $this->apply_file_template( $file_template,  $this->get_state_properties() );

			ob_start();

			$generator_slug =  static::generator_slug();

			if ( ! is_file( $template_file = $this->template_dir( "{$generator_slug}.php" ) ) ) {

				Loader::log_error( "Template file {$template_file} does not exist." );

			}
			extract( array( $generator_slug => $this->object ), EXTR_SKIP );

			unset( $file_template, $generator_slug );

			require( $template_file );

			$source = ob_get_clean();

			file_put_contents( $filepath, $source );


		}

		/**
		 * @param string $generator_slug
		 * @param string $namespace
		 *
		 * @return bool
		 */
		function get_generator_class( $generator_slug, $namespace ) {

			$generator_slug = implode( '_', array_map( 'ucfirst', explode( '_', $generator_slug ) ) );

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

			if ( preg_match_all( '#(.*?)\{([^\}]+)\}#', $file_template, $matches ) ) {

				$root_properties = Loader::get_state( self::$root )->data;

				$messages = array();

				$values = array();

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

							$properties = Loader::get_state( $value )->data;

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

					if (is_null( $parent_value ) ) {

						echo '';

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
