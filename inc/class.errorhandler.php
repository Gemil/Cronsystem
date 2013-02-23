<?php
class FatalException extends Exception {}
class DatabaseException extends Exception {}

class ErrorHandler {
	public function __construct() {
		set_exception_handler(array($this,"exception_handler"));
		set_error_handler(array($this, "error_handler"));
	}
	public function error_handler($errno, $errstr, $errfile, $errline) {
		if( error_reporting() == 0 ) return false; //Wenn unterdrückter Fehler abbrechen
		Log::fatal("Error: ".$errno." ".$errstr." ".$errfile." ".$errline);
		return false; //Interne Abhandlung von PHP abschalten
	}
	public function exception_handler($e) {
		Log::fatal("Exeption: ".$e->getMessage());
	}
}

?>