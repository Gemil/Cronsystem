<?php
class IOShell extends IOBase implements IO  {
	private $stdin;
	private $modus;
	
	public function __construct($io, $last = 0) {
		if( ENV::getOS() == 'winnt' ) {
			Log::warn( 'Input STDIN: Cant load on Windows cause of a php bug: https://bugs.php.net/bug.php?id=34972' );
			return;
		}
		$this->modus = 'view';
		$this->last = $last;
		$this->stdin = fopen( "php://stdin", 'r' );
		stream_set_blocking( $this->stdin, false );
	}
	
	public function run() {
		$this->log();
	
		if( ENV::getOS() == 'winnt' ) return;
		$read = '';
		while(($resp = fgetc($this->stdin)) !== false) {
			$read .= $resp;
		}
		
		if (preg_match( "/\n/", $read)) {
			$input = trim($read);
			switch ($this->modus) {
				case "view":
					switch (true) {
						case ($input == 'help'):
							echo "==============================\n";
							echo "=       Welcome to Cron      =\n";
							echo "=   This is the help Center  =\n";
							echo "= help - This Help shows up  =\n";
							echo "==============================\n";
							echo "job debug - Ausgabe des Jobarrays\n";
							echo "exit - Beenden des Systems\n";
							echo "io disable <name> - Beenden des IOs\n";
							echo "io enable <name> - Starten des IOs\n";
							echo "\n";
							break;
						case (preg_match('/exit/', $input, $m)):
							echo "Stop eingeleitet...\n";
							Manager::stopJobs();
							Manager::markForRestart();
							break;
						case (preg_match('/job debug/', $input, $m)):
							var_dump(Manager::getJobs());
							break;
						case (preg_match('/io disable ([a-zA-Z]+)/', $input, $m)):
							IOLoader::disableInput($m['1'], "Command from IOShell");
							break;
						case (preg_match('/io enable ([a-zA-Z]+)/', $input, $m)):
							IOLoader::enableInput(IOLoader::getIObyName($m[1]));
							break;
						default:
							echo "Command not found\n";
							break;
					}
					break;
				case "admin":
					break;
				default:
					break;
			}
		}
		elseif (preg_match( "/\t/", $read)) {
			$input = trim($read);
			// Etwas machen wenn Tab gedrückt wurde (z.b. Autovervollständigung?)
		}
	}
	
	public function log() {
		
		foreach(Log::getLog($this->last) as $logentry) {
			echo "[". date("H:i:s", $logentry['timestamp']) ."]".$logentry['message']."\n";
		}
		$this->last = microtime(true);
	}
}
?>