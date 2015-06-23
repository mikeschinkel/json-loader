<?php

namespace JSON_Loader {

	/**
	 * Class State
	 *
	 * Contains the state for an object as well as it's schema.
	 *
	 */
	class State extends Base {

		/**
		 * @var string Namespace to try
		 */
		var $namespace = '\\';

		/**
		 * @var mixed[]|Property[] Schema that drives instantiation
		 */
		var $schema = array();

		/**
		 * @var array Data values loaded from JSON
		 */
		var $data = array();

		/**
		 * @var string The filepath of the JSON file, if loaded from a file
		 */
		var $filepath;

		/**
		 * @var Logger
		 */
		var $logger;

		/**
		 * @var Object
		 */
		var $parent;

		/**
		 * @param array|object|string $parent
		 * @param array $args {
		 *      @type string $namespace
		 * }
		 */
		function __construct( $parent = null, $args = array() ) {

			$this->parent = $parent;

			$this->schema = array();

			$this->data = array();

			parent::__construct( $args );

		}
	}


}
