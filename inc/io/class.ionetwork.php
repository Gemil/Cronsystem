<?php
class IONetwork extends IOBase implements IO  {
	private $sock = false;
	private $connections;
	
	public function __construct($io, $last=0) {
		if( !$this->sock ) {
			$this->sock = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
			socket_bind( $this->sock, $io['server'], $io['port'] );
			socket_listen( $this->sock, 10 );
			socket_set_nonblock( $this->sock );	
			
			$this->connections[] = new Connectionhandler( $this->sock );
		}
	}
	
	public function run() {
		foreach( $this->connections as $k => $conn ) {
			if( $conn->conn == $this->sock ) {
				//Prüfen ob es neue Verbindungen gibt
				while( ( $newc = @socket_accept( $conn->conn ) ) !== false ) {
					$ip = '';
					socket_getpeername( $newc, $ip );
					socket_set_nonblock( $newc );
					
					Log::info( 'Inputnetwork: New Client has connected. IP '. $ip );

					$temp = $this->connections[] = new Connectionhandler( $newc );
					$temp->sendAuthReq();
				}
			} else {
				if( $conn->tick() == false ) unset( $this->connections[$k] );
			}
		}
	}
	
	public function __destruct() {
		foreach( $this->connections as $k => $conn ) {
			socket_close( $conn->conn );
		}
	}
}
?>