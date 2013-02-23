<?php
class ENVBase {
	protected static $_cores = 0;
	protected static $_memory = 0;
	protected static $_usedmemory = 0;
	protected static $_freememory = 0;
	protected static $_cachememory = 0;
	protected static $_buffermemory = 0;
	protected static $_usedcpu = 0;
	protected static $_os = '';

	public static function getCores() {
		return self::$_cores;
	}
	
	public static function getOS() {
		return self::$_os;
	}
	
	public static function getMemory() {
		return self::$_memory;
	}
	
	public static function getUsedMemory() {
		return self::$_usedmemory;
	}
	
	public static function getFreeMemory() {
		return self::$_freememory;
	}
	
	public static function getUsedCPU() {
		return self::$_usedcpu;
	}
}