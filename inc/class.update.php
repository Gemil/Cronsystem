<?php
class Update {
	private static $ftp;

	public static function checkUpdate() {
		global $update, $dir;
	
		if( !Manager::isParent() ) {
			Log::info("Manager: Incoming function request from a child on a parent only function");
			return false;
		}
		
		self::$ftp = @ftp_connect( $update['server'] );
		if( !self::$ftp ) {
			Log::error( "Could not establish a connection to Update Server" );
			return false;
		}
		
		if( !@ftp_login( self::$ftp, $update['user'], $update['pass'] ) ) {
			ftp_close( self::$ftp );
			Log::error( "Login to the Update Server is wrong" );
			return false;
		}
		
		if( !@ftp_chdir( self::$ftp, "/" ) ) {
			ftp_close( self::$ftp );
			Log::error( "Cant chdir to / on Update Server" );
			return false;
		}
		
		if( !@ftp_get( self::$ftp, $dir['temp'] . '/versions.json', 'versions.json', FTP_ASCII ) ) {
			ftp_close( self::$ftp );
			Log::error( "Cant download the version file" );
			return false;
		}
		
		if (!is_readable($dir['temp'] . '/versions.json') || !is_readable($dir['root'] . '/versions.json')) {
			Log::error( "JSON is not readable" );
			return false;
		}
		$versions = unserialize( file_get_contents( $dir['temp'] . '/versions.json' ) );
		$localversions = unserialize( file_get_contents( $dir['root'] . '/versions.json' ) );
		
		if( count( $versions ) > count( $localversions ) ) {
			//Es sind neue Patches vorhanden
			//Alle Kinder anhalten
			Manager::stopJobs();
			
			foreach( $versions as $key => $version ) {
				if( !isset( $localversions[$key] ) ) {
					Log::info( "Found Patch with Number ". $version['id'] ." which is going to be installed" );
					if( self::applyPatch( $version ) ) { 
						$localversions[$key] = $version;
						file_put_contents( $dir['root'] . '/versions.json', serialize( $localversions ) );
						ftp_close( self::$ftp );
						Log::info( "Installed Patch restarting..." );
						Manager::markForRestart();
					} else {
						ftp_close( self::$ftp );
						Log::fatal( "Error during install of Patch with ID ". $version['id'] );
						return false;
					}
				}
			}
		}
	}
	
	private static function applyPatch( $patch ) {
		global $dir;
	
		if( ftp_pwd( self::$ftp ) != "/packages" ) {
			if( !@ftp_chdir( self::$ftp, "/packages" ) ) {
				Log::error( "Cant chdir to /packages on Update Server" );
				return false;
			}
		}
		
		if( !@ftp_get( self::$ftp, $dir['temp'] . '/package.zip', $patch['file'] . ".zip", FTP_BINARY ) ) {
			ftp_close( self::$ftp );
			Log::error( "Cant download the patch file" );
			return false;
		}
		
		$zip = new ZipArchive();
		if( ( $res = @$zip->open( $dir['temp'] . '/package.zip' ) ) == true ) {
			$zip->extractTo( $dir['temp'] . '/package/' );
			$zip->close();
		} else {
			$zip->close();
			Log::error( "Zip returned an error during a patch: ". $res );
			return false;
		}
		
		self::copy( $dir['temp'], $dir['root'] );
		
		return true;
	}
	
	private static function copy( $from, $to ) {
		foreach( glob( $from ."/*" ) as $file ) {
			if( is_dir( $file ) ) {
				$new = str_replace( $from, '', $file );
				if( !is_dir( $to . "/" . $new ) ) mkdir( $to . "/" . $new, 0644 ); 
				self::copy( $file, $to . "/" . $new );
			} else {
				$new = str_replace( $from, '', $file );
				copy( $file, $to ."/". $new );
			}
		}
	}
}