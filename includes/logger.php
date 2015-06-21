<?php

namespace JSON_Loader {

	/**
	 * Class Logger
	 */
	class Logger {

		/**
		 * @param $log_text
		 */
		function notice( $log_text ) {
			fwrite( STDOUT, "\nNotice: {$log_text}\n" );
		}

		/**
		 * @param $log_text
		 */
		function warning( $log_text ) {
			fwrite( STDOUT, "\nWARNING: {$log_text}\n" );
		}

		/**
		 * @param $log_text
		 */
		function error( $log_text ) {
			fwrite( STDERR, "\nERROR: {$log_text}\n" );
			die( 1 );
		}

		/**
		 * @param $log_text
		 */
		function message( $log_text ) {
			echo "{$log_text}\n";
		}

	}

}


