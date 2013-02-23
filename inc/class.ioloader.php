<?php
class IOLoader {
	private static $ios = array();
	private static $disabled = array();
	
	/*
	 * Laden aller standart IOs
	 */
	public static function init() {
		global $io;
		if (!Manager::isParent()) {
			Log::info("Enviroment: Incoming function request from a child on a parent only function");
			return false;
		}
		
		//Alle "alten" Interfaces abbauen
		if( count( self::$ios ) > 0 ) {
			foreach( self::$ios as $k => $io ) {
				unset( self::$ios[$k] );
			}
			
			self::$ios = array();
		}
		
		foreach ($io as $input) {
			if(!self::isDisabled($input['type'])) self::loadInput($input);
		}
		
		if(self::countIO() == 0) {
			Log::error("No Inputs loaded!");
		}
	}
	
	/*
	 * Laden eines IOs
	 */
	public static function loadInput($io) {
		if (!Manager::isParent()) {
			Log::info("Enviroment: Incoming function request from a child on a parent only function");
			return false;
		}
		try {
			self::enableInput($io);
		}
		catch( Exception $e ) {
			self::disableInput($io['type'], $e->getMessage());
		}
	}
	
	/*
	 * Disablen eines IOs
	 */
	public static function disableInput($name, $log = "undefined") {
		if (isset(self::$ios[$name]) && is_object(self::$ios[$name])) {	
			self::$disabled[$name] = array("io"=>self::$ios[$name], "last"=>self::$ios[$name]->getLast());
			unset(self::$ios[$name]);
		}
		else {
			self::$disabled[$name] = array("io"=>true, "last"=>0);
		}
		Log::warn("Disabling input ".$name.". Reason: ".$log);
	}
	
	/*
	 * Enablen eines IOs
	 */
	public static function enableInput($io, $last=0) {
		$class = 'IO'.$io['type'];
		self::$ios[$io['type']] = new $class($io, $last);
		if (self::isDisabled($io['type'])) {
			unset(self::$disabled[$io['type']]);
		}
		Log::info("Input: ".$class." loaded successfully");
	}
	
	/*
	 * Prüft ob IO Disablen ist
	 */
	private static function isDisabled($name) {
		return array_key_exists($name, self::$disabled);
	}
	
	/*
	 * Liefert IO Daten anhand des Typs
	 */
	public static function getIObyName($name) {
		global $io;
		foreach ($io as $input) {
			if ($input['type'] = $name) {
				return $input;
			}
		}
		return false;
	}
	
	/*
	 * Ein Durchlauf der IOs
	 */	
	public static function run() {
		if (!Manager::isParent()) {
			Log::info("Enviroment: Incoming function request from a child on a parent only function");
			return false;
		}
		
		foreach( self::$ios as $name => $input ) {
			try {
				$input->run();
			} catch( Exception $e ) {
				self::disableInput($name, $e->getMessage());
			}
		}
		if (Manager::doEachTicks(60)) {
			foreach (self::$disabled as $name => $input) {
				if (is_object($input['io'])) {
					try {
						if ($input['io']->check()) {
							self::enableInput(self::getIObyName($name), $input['last']);
						}
					}
					catch (Exception $e) {
						Log::info("Disabled input could not been restored");
					}
				}
			}
		}
	}
	
	/*
	 * Zählen der aktiven IOs
	 */	
	public static function countIO() {
		return sizeof(self::$ios);
	}
}