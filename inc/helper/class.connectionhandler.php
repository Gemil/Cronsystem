<?php
class Connectionhandler {
	private $status;
	private $user;
	private $loggedin = false;
	private $showlog = false;
	private $last = 0;
	private $current = 1;
	private $njob = array();
	public $conn;

	public function __construct( $conn ) {
		$this->conn = $conn;
	}
	
	public function sendAuthReq() {
		$this->sendTo( "User: " );
		$this->status = "USER";
	}
	
	private function sendTo( $msg ) {
		$len = strlen( $msg );
		
		$sent = socket_write( $this->conn, $msg, $len );
		while( $sent != $len ) {
			$sent += socket_write( $this->conn, substr( $msg, $sent ), ($len-$sent) );
		}
	}
	
	public function tick() {
		if( $this->loggedin == true ) {
			//Alles was es so zu schreiben gibt
			if( $this->showlog == true ) {
				//Logviewer ist an				
				foreach(Log::getLog($this->last, false) as $logentry) {
					$this->sendTo( "[". $logentry['timestamp'] ."]".$logentry['message']."\n\r" );
				}
				
				$this->last = time()-2;
			}
		}
		
		$read = socket_read( $this->conn, 1024 );
		if( $read == false ) return true;
		
		switch( $this->status ) {
			case 'USER':
				$read = trim( $read );
				Log::info( 'Inputnetwork: Received a User login request for '. $read );
				$this->status = 'PASS';
				$this->user = $read;
				$this->sendTo( "Pass: " );
				break;
			
			case 'PASS':
				Log::info( 'Inputnetwork: Received a Password for '. $this->user );
				if( $this->user == 'test' && trim( $read ) == 'test' ) {
					$this->loggedin = true;
					Log::info( 'Inputnetwork: User '. $this->user .' logged in' );
					$this->sendWelcome();
					$this->status = 'RDY';
				}
				
				break;
				
			case 'JOBTYPE':
				if( $this->loggedin != true ) {
					socket_close( $this->conn );
					unset( $this );
				}
				
				switch( trim( $read ) ) {
					case 'period':
						$this->njob['type'] = 'period';
						$this->sendTo( "Interval plox:" );
						$this->status = 'JOBINTERVAL';
						break;
						
					case 'live':
						$this->njob['type'] = 'live';
						$this->status = 'JOBSTART';
						$this->sendTo( "Starttime plox (d.m.y, H:i:s):" );
						break;
						
					case 'onetime':
						$this->njob['type'] = 'onetime';
						$this->status = 'JOBSTART';
						$this->sendTo( "Starttime plox (d.m.y, H:i:s):" );
						break;
				
					default:
						$this->njob = array();
						$ip = '';
						socket_getpeername( $this->conn, $ip );
						$this->sendTo( "Invalid Job Type. Aborting\n\r" );
						Log::warn( 'Inputnetwork: '. $ip .' gave an invalid Job Type' );
						$this->status = 'RDY';
						return true;
						break;
				}
				
				break;
				
			case 'JOBINTERVAL':
				if( @is_numeric( trim( $read ) ) ) {
					$this->njob['period'] = (int)( trim( $read ) );
					$this->status = 'JOBSTART';
					$this->sendTo( "Starttime plox (d.m.y, H:i:s):" );
				} else {
					$this->njob = array();
					$ip = '';
					socket_getpeername( $this->conn, $ip );
				
					$this->sendTo( "Invalid Job Interval. Aborting\n\r" );
					Log::warn( 'Inputnetwork: '. $ip .' gave an invalid Job Interval' );
					
					$this->status = 'RDY';
				}
				
				break;
				
			case 'JOBSTART':
				if( ( $time = strtotime( trim( $read ) ) ) !== false ) {
					$this->njob['next'] = $time;
					$this->status = 'JOBCODE';
					$this->sendTo( "PHP Code plox (php-quit for end)\n\r" );
				} else {
					$this->njob = array();
					$ip = '';
					socket_getpeername( $this->conn, $ip );
				
					$this->sendTo( "Invalid Job Starttime. Aborting\n\r" );
					Log::warn( 'Inputnetwork: '. $ip .' gave an invalid Job Starttime' );
					
					$this->status = 'RDY';
				}
			
				break;
				
			case 'JOBCODE':
				if( !isset( $this->njob['code'] ) ) {
					$this->njob['code'] = "<?php\n";
				}
				
				if( trim( $read ) == 'php-quit' ) {
					$ip = '';
					socket_getpeername( $this->conn, $ip );
					Log::info( 'Inputnetwork: '. $ip .' quits the PHP Editor' );
					
					$this->njob['config'] = array();
					Manager::addJob( $this->njob );
					
					$this->sendTo( "Added the Job GZ\n\r" );
					
					$this->njob = array();
					$this->status = 'RDY';
					
					return true;
				}
				
				$this->njob['code'] .= trim( $read ) ."\n";
				
				break;
				
			default:
				if( $this->loggedin != true ) {
					socket_close( $this->conn );
					unset( $this );
				}
				
				$ip = '';
				socket_getpeername( $this->conn, $ip );
				
				switch( true ) {
					case ( trim( $read ) == 'help' ):
							Log::info( 'Inputnetwork: '. $ip .' is showing help' );
							$this->sendHelp();
						break;
						
					case (preg_match('/job add ([a-zA-Z]+)/', trim( $read ), $m)):
							$this->njob = array();
							
							Log::info( 'Inputnetwork: '. $ip .' wanted to create a new Job' );
							
							$this->status = 'JOBTYPE';
							$this->njob['name'] = $m[1];
							$this->sendTo( "Type of the Job(Period|Ontime|Live): " );

						break;
						
					case ( trim( $read ) == 'quit' ):
							Log::info( 'Inputnetwork: '. $ip .' disconnects' );
							
							socket_close( $this->conn );
							
							unset( $this );
							
							return false;
						break;
						
					case ( preg_match( "/log (0|1)/", $read, $m ) ):
						$this->showlog = (bool)$m[1];
						
						if( $this->showlog == true ) {
							Log::info( 'Inputnetwork: '. $ip .' has enabled the log viewer' );
						}
						
						if( $this->showlog == false ) {
							Log::info( 'Inputnetwork: '. $ip .' has disabled the log viewer' );
						}
						
						break;
				}
		}
		
		return true;
	}
	
	private function sendWelcome() {
		$this->sendTo( "Welcome ". $this->user ." Type help for help :D\n\r" );
	}
	
	private function sendHelp() {
		$this->sendTo( "job add {name} = Starts the Job Add wizard\n\r" );
		$this->sendTo( "log {0|1} = Enable or disable viewing logs\n\r" );
		$this->sendTo( "quit = Closes the connection\n\r" );
	}
}