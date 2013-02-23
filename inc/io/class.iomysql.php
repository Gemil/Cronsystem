<?php 
class IOMysql extends IOBase implements IO {
	private $db;
	private $server;
	private $io;
	
	public function __construct($io, $last=0) {
		if (!isset($io['port'])) { 
			$io['port'] = 3306; 
		}
		$this->io = $io;
		$this->last = $last;
		$this->db = new Database($this->io['server'], $this->io['username'], $this->io['password'],  $this->io['database'], $this->io['prefix']);

		$this->server = $this->getServer();
		Log::info("Input: IOMysql -> Setting ServerID to ".$this->getServerID());
	}
	
	/*
	 * Guckt nach neuen Updates bei diesem IO
	 */
	public function run() {
		$run = true;
		while( $run ) {
			try {
				$this->logs();
				$this->jobs();
				$this->server();
				$run = false;
			}
			catch(DatabaseException $e ) {
				if (DEBUG) echo $e->getMessage()."\n";
				Log::warn($e->getMessage());
				$this->db = new Database($this->io['server'], $this->io['username'], $this->io['password'],  $this->io['database'], $this->io['prefix']);
			}
		}
	}
	
	/*
	 * Checken ob DB wieder da ist
	 */
	public function check() {
		$check = fsockopen($this->io['server'], $this->io['port'], $errno);
		if ($check && $errno == 0){
			return true;
		}
		return false;
	}
	
	/*
	 * F�gt den Log in die Datenbank ein
	 */
	public function logs() {
		$log = Log::getLog($this->last);
		$insert = array();
		foreach($log as $logentry) {
			$temp = array();
			$temp['serverid'] = $this->getServerID();
			$temp['logdate'] = $this->date($logentry['timestamp']);
			$temp['prio'] = $logentry['prio'];
			$temp['message'] = $logentry['message'];
			$insert[] = $temp;
		}
		$this->db->insert_multi("cron_log", $insert);
		$this->last = microtime(true);
	}
	
	/*
	 * Guckt nach allen Jobs in der Datenbank
	 */
	public function jobs() {
		// Jobstatus in die DB schreiben
		
		$jobs_pool = Manager::getJobs();
		
		// Jobs f�r diesen Server aus der DB holen
		if (!Manager::isRestart()) {
			$jobs_db = $this->db->select(array("cron_server_has_job","cron_job"), "*", "serverid = '".$this->getServerID()."'");
			$jobadded = false;
			while ($job = $this->db->fetch_array($jobs_db)) {
				if ($job['active'] == "1" && $job['running'] == "0" && !array_key_exists($job['name'], $jobs_pool)) {
					// wird nur ausgeführt, wenn der Job zurzeit nicht läuft UND er nicht schon im Jobpool existiert
					Manager::addJob($job);
				}
				elseif (array_key_exists($job['name'], $jobs_pool) && $job['active'] == "0" && $jobs_pool[$job['name']]['running'] != true && $job['running'] == 0) {
					// Wenn der Job noch im Pool ist, aber deaktiviert wurde
					Manager::removeJob($job['name']);
				}
				elseif ($job['reloadconfig'] == 1 &&$job['active'] == "1" && array_key_exists($job['name'], $jobs_pool) && $jobs_pool[$job['name']]['running'] != true) {
					// Wenn Job aktiv ist, jedoch die Config/Code geupdatet wurde
					$this->db->update("cron_server_has_job", array("reloadconfig"=>0), "shjid='".$job['shjid']."'");
					Manager::updateJob($job);
				}
				// Wird ausgeführt wenn es eine neue Laufzeit gibt
				if (array_key_exists($job['name'], $jobs_pool)) {
					if ($jobs_pool[$job['name']]['lastruntime'] != 0) {
						Manager::resetJobRuntime($job['name']);
						$this->db->insert("cron_runtimes", array("shjid"=> $job['shjid'], "executiontime" => $this->date(), "runtime"=>$jobs_pool[$job['name']]['lastruntime']));
					}
				}
			}
			
		}
		foreach ($jobs_pool as $job) {
			if (isset($job['shjid'])) {
				// wird ausgef�hrt falls es nen db job is
				$updates['running'] = ($job['running'] === false)?0:1;
				$updates['next'] = $this->date($job['next']);
				$updates['lastruntime'] = $job['lastruntime'];
				$this->db->update("cron_server_has_job", $updates, "shjid = '".$job['shjid']."'");
			}
		}
		
	}
	
	/*
	* Holt sich die Serverdaten
	*/
	public function getServer() {
		$result = $this->db->select("server", "*", "name = '".php_uname("n")."'");
		
		if($this->db->num_rows($result) != 1) {
			if($this->db->num_rows($result) == 0 ) {
				$server = $this->db->insert("server", array("name"=>php_uname("n")));
				return array("serverid" => $server, "name" => php_uname("n"));
			}			
			elseif($this->db->num_rows($result) > 1) {
				Log::fatal("Input: IOMysql - Doppelte Servereinträge gefunden für ".php_uname("n"));
			}
		}		
		else {
			$return = $this->db->fetch_array($result);
			if ($return['name'] == php_uname("n")) {
				return $return;
			}
		}
		
		return false;
	}
	
	/*
	 * Servertick
	 */
	public function server() {
		$this->db->update("server", array("lastaccess"=>$this->date()), "serverid='".$this->getServerID()."'");
	}
	
	/*
	* Liefert die ServerID zur�ck
	*/
	public function getServerID() {
		return $this->server['serverid'];
	}
	
	public function date($timestamp=0) {
		if ($timestamp == 0) {
			$timestamp = time();
		}
		return date('Y-m-d H:i:s',$timestamp);
	}
}
?>