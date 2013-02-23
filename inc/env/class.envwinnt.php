<?php
class ENV extends ENVBase {
	private static $_parentpid;
	
	public static function init() {
		if( !isset( self::$_parentpid ) ) {
			self::$_parentpid = getmypid();
			Log::info( "Enviroment: Setting ParentPID to: ". self::$_parentpid );
		}
		
		if( getmypid() != self::$_parentpid ) {
			Log::info( "Enviroment: Incoming function request from a child on a parent only function" );
		}
		
		else {
			parent::$_os = 'winnt';
			parent::$_cores = 0;
			parent::$_memory = 0;
		
			$wmi = new COM( "winmgmts://localhost/root/CIMV2" );
			$cpus = $wmi->ExecQuery( "select NumberOfLogicalProcessors, TotalPhysicalMemory from Win32_ComputerSystem" );
			
			foreach( $cpus as $cpu ) {
				parent::$_cores += $cpu->NumberOfLogicalProcessors;
				parent::$_memory += $cpu->TotalPhysicalMemory;
			}
			
			Log::info( "Enviroment: Detected ". parent::$_cores ." cores on this Server" );
			Log::info( "Enviroment: Detected ". ( round( parent::$_memory / ( 1024*1024*1024 ) ) )." GB of RAM on this Server" );
		}
	}
}