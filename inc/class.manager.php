<?php

class Manager {
	private static $jobs=array();
	private static $pcm;
	private static $parentpid = 0;
	private static $ticks = 0;
	private static $restart = false;
	
	public static function init() {
		if (self::$parentpid == 0) {
			self::$parentpid = getmypid();
		}
		
		IOLoader::init();
		$pcm = "PCM".ENV::getOS();
		self::$pcm = new $pcm();		
	}
	
	/*
	 * Ein Durchlauf des Cronsystems
	 */
	public static function isRestart () {
		if (self::$restart == true) {
			return true;
		}
		return false;
	}
	public static function tick() {
		if (!self::isParent()) {
			Log::info("Manager: Incoming function request from a child on a parent only function");
			return false;
		}
		
		IOLoader::run();
		
		if( self::$restart == true ) {
			exit(0);
		}
		
		self::$pcm->check();
		
		try {
			foreach (self::$jobs as $k => $job) {
				if( self::checkJob( $job ) ) {
					// Log::info("Manager: Start Job - ".$job['name']);
					
					// Kann noch nicht detecten ob Job beendet wird
					if(self::$pcm->newChild($job)) {
						self::$jobs[$k]['laststart'] = microtime(true);
						self::$jobs[$k]['running'] = true;
					}
					
					self::$jobs[$k]['next'] = time() + self::$jobs[$k]['period'];
				}
			}
		}
		catch (Exception $e) {
			Log::fatal("Unexpected error: ".$e->getMessage());
		}
		
		self::$ticks++;
		
		if( self::$ticks > 120000 ) {
			self::$ticks -= 10000;
		}
	}
	
	/*
	 * F�gt einen Job zum Pool hinzu
	 */
	public static function addJob( array $job ) {
		if( !isset( $job['name'] ) || !is_string( $job['name'] ) ) {
			Log::warn( 'Wanted to add a Job without a Name' );
			return;
		}
	
		if( !isset( $job['code'] ) || !is_string( $job['code'] ) ) {
			Log::warn( 'Wanted to add a Job without Code' );
			return;
		}
		$job['uuid'] = md5( time() + $job['name'] );
		$job['running'] = false;
		$job['next'] = time();
		$job['period'] = (int)$job['period'];
		$job['laststart'] = 0;
		$job['lastend'] = 0;
		$job['lastruntime'] = 0;
		$job['runtime_count'] = (isset($job['runtime_count']))?$job['runtime_count']:0;
		if( !isset( $job['period'] ) || !is_numeric( $job['period'] ) ) {
			Log::warn( 'Wanted to add a Period Job without a period time' );
			return;
		} 

		self::loadJob( $job );
		self::$jobs[$job['name']] = $job;
				
		
		return;
	}
	
	/*
	 * L�scht einen Job vom Job Pool
	 */
	public static function removeJob($jobname) {
		global $dir;
		if (is_file($dir['jobs']."/".self::$jobs[$jobname]['uuid'].".inc.php")) unlink($dir['jobs']."/".self::$jobs[$jobname]['uuid'].".inc.php");
		if (is_file($dir['temp']."/".self::$jobs[$jobname]['uuid'].".jobconfig")) unlink($dir['temp']."/".self::$jobs[$jobname]['uuid'].".jobconfig");

		unset(self::$jobs[$jobname]);
	}
	
	/*
	 * Updated die Konfiguration eines Jobs
	 */
	public static function updateJob(array $job) {
		$job['uuid'] = self::$jobs[$job['name']]['uuid'];
		$job['running'] = false;
		$job['next'] = time();
		$job['period'] = (int)$job['period'];
		$job['laststart'] = 0;
		$job['lastend'] = 0;
		$job['lastruntime'] = 0;
		$job['runtime_count'] = (isset($job['runtime_count']))?$job['runtime_count']:0;
		self::loadJob ($job);
		self::$jobs[$job['name']] = $job;
	}
	
	/*
	 * L�d einen neuen Job herunter
	 */
	public static function loadJob( array $job ) {
		global $dir;
		$newjob = fopen( $dir['jobs']."/".$job['name'].".inc.php", "w+" );
		fwrite( $newjob, $job['code'] );
		fclose( $newjob );
		Log::info( "Job loaded: ". $job['name'] );
	}

	/*
	 * Pr�ft, ob ein Job ausgef�hrt werden soll
	 */
	public static function checkJob( array $job ) {
		if( time() >= $job['next'] && $job['running'] == false ) return true;
		return false;
	}
	/*
	 * Lastruntime resetten
	 */
	public static function resetJobRuntime($jobname) {
		self::$jobs[$jobname]['lastruntime'] = 0;
	}
	/*
	 * Markiert einen Job als beendet
	 */
	public static function endJob($jobname) {
		//Log::info("Manager: End Job - ".$jobname);
		self::$jobs[$jobname]['running'] = false;
		self::$jobs[$jobname]['lastend'] = microtime(true);
		if (self::$jobs[$jobname]['lastend'] > self::$jobs[$jobname]['laststart']) {
			self::$jobs[$jobname]['lastruntime'] = self::$jobs[$jobname]['lastend'] - self::$jobs[$jobname]['laststart'];
		}
	}
	
	public static function stopJobs() {
		self::$pcm->endAllThreads();
		foreach (self::$jobs as $job) {
			self::endJob($job['name']);
		}
	}
	
	/*
	 * Neustart einleiten
	 */
	public static function markForRestart() {
		if( self::isParent() ) {
			self::$restart = true;
		}
	}
	
	/*
	 * Job Informationen abrufen
	 */
	public static function getJobStatus($jobname) {
		if (array_key_exists($jobname, self::$jobs)) {
			return self::$jobs[$jobname]['running'];
		}
		return false;
	}
	
	/*
	 * Alle Jobs abrufen
	 */
	public static function getJobs() {
		return self::$jobs;
	}
	
	/*
	 * Ticks holen
	 */
	public static function doEachTicks($each) {
		if (self::$ticks%$each == 0) return true;
		return false;
	}
	
	/*
	 * Pr�ft ob dieser Pro�ess ein Kind ist
	 */
	public static function isParent() {
		if (self::$parentpid == getmypid()) {
			return true;
		}
		return false;
	}
	
}



?>