<?php
/*
** Jobklasse von Gerrit Paepcke
*/
class Job {
	private $job;
	private $start;
	private $end;
	
	public function __construct($job) {
		$this->job = $job;
		$this->start();
	}

	public function start() {
		try {
			$this->execute();
		}
		catch (Exception $e) {
			Log::warn("Runtime Error in Job: ".$this->job['name']);
		}
	}
	public function execute() {
		global $dir;
	
		if (file_exists($dir['jobs']."/".$this->job['name'].".inc.php")) {
			include($dir['jobs']."/".$this->job['name'].".inc.php");
		}
		else {
			Log::warn("Include not found: ".$dir['root']."/".$dir['jobs']."/".$this->job['name'].".inc.php");
		}

	}

}

?>