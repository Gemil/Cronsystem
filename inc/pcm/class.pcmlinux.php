<?php
/*
** Prozessklasse von Gerrit Paepcke
*/
declare(ticks=1);

class PCMlinux extends PCMBase {
	private $currentJobs = array();
   
	public function __construct(){
		Log::info("PCM (Linux) loaded successfully");
	}
	
	/*
	** Achtet darauf, das alle Kindprozesse beendet sind, bevor der Vater beendet wird. 
	*/
	public function finish(){
		sleep(1);
		while(count($this->currentJobs)){
			sleep(1);
		}
	}
	
	public function endAllThreads() {
		$this->signal(SIGKILL);
	}
	
	private function signal($sig) {
		foreach ($this->currentJobs as $pid => $prozess) {
			posix_kill($pid, $sig);
		}
	}
	
	/*
	** Legt ein neues Kind an und f�hrt die angegebene Funktion in dem Kind aus. 
	*/
	public function newChild($job){
		$pid = pcntl_fork();
		if($pid == -1){
			Log::error("Neues Kind konnte nicht gestartet werden");
			return false;
		}
		elseif ($pid){
			$this->currentJobs[$pid] = $job;
		}
		else {
			$temp = new Job($job);
			unset( $temp );
			exit(0);
		}
		
		return true;
	}
	
	public function check() {
		$pid = pcntl_waitpid(-1, $status, WNOHANG);
		while($pid > 0){
			if($pid && isset($this->currentJobs[$pid])){
				$exitCode = pcntl_wexitstatus($status);
				if($exitCode != 0){
					Log::warn('Kind unerwartet beendet');
				} 
				Manager::endJob($this->currentJobs[$pid]['name']);			
  	 	 		unset($this->currentJobs[$pid]);
   	 		}
			
   	 		$pid = pcntl_waitpid(-1, $status, WNOHANG);
		}
	}
	
	/*
	** Gibt Objekt Attribute zur�ck
	*/
	public function __get($name) {
		return $this->$name;
	}	
}