<?php 
class PCMwinnt extends PCMBase {
	private $threads = array();
	private $pipes = array();

	public function newChild( $job ) {
		global $dir;
		
		//Share erstellen
		$config = serialize( $job['config'] );
		file_put_contents( $dir['temp'] ."/". $job['uuid'] .".jobconfig", $config );
		
		//
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // STDIN ist eine Pipe, von der das Child liest
		   1 => array("pipe", "w"),  // STDOUT ist eine Pipe, in die das Child schreibt
		   2 => array("file", $dir['temp'] ."/error-output.txt", "a") // STDERR ist eine Datei,
															// in die geschrieben wird
		);
		
		//Prozess starten
		$this->threads[$job['uuid']] = proc_open( "php windows.php - ". $job['uuid'], $descriptorspec, $pipes );
		$this->pipes[$job['uuid']] = $pipes;
		
		return true;
	}
	
	public function check() {
		foreach( $this->threads as $k => $v ) {
			$status = proc_get_status( $v );
			if( $status['running'] == false ) {
				Log::info( 'PCM: Job with UUID '. $k .' finished' );
				
				foreach( $this->pipes[$k] as $k1 => $v1 ) {
					fclose( $this->pipes[$k][$k1] );
				}
				
				proc_close( $this->threads[$k] );
				
				unset( $this->threads[$k] );
				unset( $this->pipes[$k] );
			}
		}
	}
}
?>