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
			parent::$_os = 'linux';
			
			$cpuinfos = file_get_contents("/proc/cpuinfo");
			$processors = preg_split('/\s?\n\s?\n/', trim($cpuinfos));
            foreach ($processors as $processor) {
            	parent::$_cores++;
            }
            $meminfos = file_get_contents("/proc/meminfo");
            $bufe = preg_split("/\n/", $meminfos, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($bufe as $buf) {
                if (preg_match('/^MemTotal:\s+(.*)\s*kB/i', $buf, $ar_buf)) {
                    parent::$_memory = $ar_buf[1] * 1024;
                }
            }
			
			Log::info( "Enviroment: Detected ". parent::$_cores ." cores on this Server" );
			Log::info( "Enviroment: Detected ". ( round( parent::$_memory / ( 1024*1024*1024 ) ) )." GB of RAM on this Server" );
		}
	}
}
?>